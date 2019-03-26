create table website_mobile_categorys (
  id          int unsigned key      auto_increment,
  category_id int          not null
  comment '分类ID',
  name        varchar(100) not null
  comment '名字',
  image       varchar(255) comment '图片',
  icon        varchar(100) comment '类目图标',
  sort        int unsigned not null default 1,
  parent_id   int unsigned not null
  comment '父类ID',
  created_at  datetime     not null,
  updated_at  datetime     not null
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci
  COMMENT = '移动端分类';


create table website_pc_categorys (
  id          int unsigned key      auto_increment,
  category_id int          not null
  comment '分类ID',
  name        varchar(100) not null
  comment '名字',
  sort        int unsigned not null default 1,
  parent_id   int unsigned not null
  comment '父类ID',
  created_at  datetime     not null,
  updated_at  datetime     not null
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci
  COMMENT = 'PC端分类';