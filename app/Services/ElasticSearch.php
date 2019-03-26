<?php
// +----------------------------------------------------------------------
// | ElasticSearch.php
// +----------------------------------------------------------------------
// | Description: 封装Elasticsearch搜索
// +----------------------------------------------------------------------
// | Time: 2018/12/4 上午10:24
// +----------------------------------------------------------------------
// | Author: wufly <wfxykzd@163.com>
// +----------------------------------------------------------------------

namespace App\Services;

use App\Models\Category\Category;

class ElasticSearch
{
    // 初始化查询
    protected $params = [
        'index' => 'products',
        'type'  => '_doc',
        'body'  => [
            'query' => [
                'bool' => [
                    'filter' => [],
                    'must'   => [],
                ],
            ],
        ],
    ];

    // 添加分页查询
    public function paginate($size, $page)
    {
        $this->params['body']['from'] = ($page - 1) * $size;
        $this->params['body']['size'] = $size;
        return $this;
    }

    // 筛选上架状态的商品
    public function onSale()
    {
        $this->params['body']['query']['bool']['filter'][] = ['term' => ['status' => 1]];
        return $this;
    }

    // 筛选最高价格的商品
    public function maxPrice($price)
    {
        $this->params['body']['query']['bool']['filter'][] = ['range' => ['price' => ["lt" => $price]]];
        return $this;
    }

    // 筛选最低价格的商品
    public function minPrice($price)
    {
        $this->params['body']['query']['bool']['filter'][] = ['range' => ['price' => ["gt" => $price]]];
        return $this;
    }

    // 按类目筛选商品
    public function category(Category $category)
    {
        if (!$category->is_final) {
            $this->params['body']['query']['bool']['filter'][] = [
                'prefix' => ['category_ids' => $category->category_ids . ',' . $category->id . ','],
            ];
        } else {
            $this->params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $category->id]];
        }
        return $this;
    }

    // 多类目筛选商品
    public function categories($categories, $isFront = false)
    {
        $category_ids = [];
        $attrValue_ids = [];
        foreach ($categories as $category) {
            if ($isFront) {
                if ($category->category_one) {
                    $category_ids = array_merge($category_ids, explode(',', $category->category_one));
                }
                if ($category->category_two) {
                    $category_ids = array_merge($category_ids, explode(',', $category->category_two));
                }
                if ($category->category_three) {
                    $category_ids = array_merge($category_ids, explode(',', $category->category_three));
                }
                if ($category->attribute_value) {
                    $attrValue_ids = array_merge($attrValue_ids, explode(',', $category->attribute_value));
                }
            } else {
                if ($category->level != 3) {
                    $where = [['level', '=', 3]];
                    if ($category->level == 1) {
                        $where[] = ['category_ids', 'like', '0,' . $category->id . ',%'];
                    } elseif ($category->level == 2) {
                        $where[] = ['category_ids', '=', $category->category_ids . ',' . $category->id];
                    }
                    $categoryThreeIds = Category::where($where)->get()->pluck('id')
                        ->toArray();
                } else {
                    $categoryThreeIds = [$category->id];
                }
                $category_ids = array_merge($category_ids, $categoryThreeIds);
            }
        }
        if ($attrValue_ids) {
            $attrValue_ids = array_unique($attrValue_ids);
            $attrValueFilter = ['terms' => ['properties.value_id.value_id' => array_values($attrValue_ids)]];
            $this->params['body']['query']['bool']['filter'][] = [
                'nested' => [
                    'path'  => 'properties.value_id',
                    'query' => [
                        // ['term' => ['properties.search_value' => $name.':'.$value]],
                        "bool" => [
                            'must' => $attrValueFilter
                        ]
                    ],
                ],
            ];
        }
        $filter = array_unique($category_ids);
        $filter_array = ['terms' => ['category_id' => array_values($filter)]];
        $this->params['body']['query']['bool']['must'][] = $filter_array;
        return $this;
    }

    // 添加搜索词
    public function keywords($keywords)
    {
        // 如果参数不是数组则转为数组
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query'  => $keyword,
                    'fields' => [
                        'good_en_title^3',
                        'good_en_summary',
                        'category_path^1',
                        'skus.description^2',
                        'prop.value^1',
                    ],
                ],
            ];
        }
        return $this;
    }

    // 分面搜索的聚合
    public function aggregateProperties()
    {
        $this->params['body']['aggs'] = [
            'properties' => [
                'nested' => [
                    'path' => 'properties',
                ],
                'aggs'   => [
                    'properties' => [
                        'terms' => [
                            'field' => 'properties.name',
                            "order" => [                    // 按学生数从大到小排序
                                                            "_count" => "desc"
                            ],
                            "size"  => 10
                        ],
                        'aggs'  => [
                            'value' => [
                                'terms' => [
                                    'field' => 'properties.value',
                                    "size"  => 20
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        return $this;
    }

    // 添加一个按商品属性筛选的条件
    public function propertyFilter($filters)
    {
        $filter_array = [];
        foreach ($filters as $index => $filter) {
            $filter_array['terms']['properties.search_value'] = [];
            foreach ($filter as $item) {
                $filter_array['terms']['properties.search_value'][] = "{$index}:{$item}";
            }
            $this->params['body']['query']['bool']['filter'][] = [
                'nested' => [
                    'path'  => 'properties',
                    'query' => [
                        // ['term' => ['properties.search_value' => $name.':'.$value]],
                        "bool" => [
                            'must' => $filter_array
                        ]
                    ],
                ],
            ];
        }
        return $this;
    }

    // 添加排序
    public function orderBy($field, $direction)
    {
        $this->params['body']['sort'][0] = ['_score' => ['order' => 'desc']];
        $this->params['body']['sort'][] = [$field => $direction];
        return $this;
    }

    // 置顶排序
    public function topOrder($ids)
    {
        $ids = str_replace('，', ',', $ids);
        $ids = array_filter(array_reverse(array_unique(explode(',', $ids))));
        foreach ($ids as $key => $id) {
            $id = (int)$id;
            if (!$id) {
                continue;
            }
            $this->params['body']['query']['bool']['should'][] = [
                'match' => [
                    'id' => ['query' => $id, 'boost' => $key + 1]
                ]
            ];
        }
        return $this;
    }

    // 返回构造好的查询参数
    public function getParams()
    {
        return $this->params;
    }
}