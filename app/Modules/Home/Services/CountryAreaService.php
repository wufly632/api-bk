<?php

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\CountryAreaRepository;
use Illuminate\Support\Facades\Cache;

class CountryAreaService
{

    const COUNTRY_INFO = '{"93":"AF","355":"AL","213":"DZ","376":"AD","244":"AO","1264":"AI","1268":"AG","54":"AR","374":"AM","61":"AU","43":"AT","994":"AZ","1242":"BS","973":"BH","880":"BD","1246":"BB","375":"BY","32":"BE","501":"BZ","229":"BJ","1441":"BM","975":"BT","591":"BO","387":"BA","267":"BW","55":"BR","1284":"VG","673":"BN","359":"BG","226":"BF","257":"BI","855":"KH","237":"CM","1":"US","238":"CV","1345":"KY","236":"CF","235":"TD","56":"CL","57":"CO","269":"KM","242":"CG","243":"CD","506":"CR","225":"CI","385":"HR","53":"CU","357":"CY","420":"CZ","45":"DK","253":"DJ","1767":"DM","1829":"DO","593":"EC","20":"EG","503":"SV","240":"GQ","291":"ER","372":"EE","251":"ET","679":"FJ","358":"FI","33":"FR","241":"GA","220":"GM","995":"GE","49":"DE","233":"GH","30":"GR","1473":"GD","502":"GT","224":"GN","245":"GW","592":"GY","509":"HT","504":"HN","852":"HK","36":"HU","354":"IS","91":"IN","62":"ID","98":"IR","964":"IQ","353":"IE","972":"IL","39":"IT","1876":"JM","81":"JP","962":"JO","7":"RU","254":"KE","686":"KI","850":"KP","82":"KR","965":"KW","996":"KG","856":"LA","371":"LV","961":"LB","266":"LS","231":"LR","218":"LY","423":"LI","370":"LT","352":"LU","853":"MO","389":"MK","261":"MG","265":"MW","60":"MY","960":"MV","223":"ML","356":"MT","692":"MH","222":"MR","230":"MU","52":"MX","691":"FM","373":"MD","377":"MC","976":"MN","382":"ME","212":"MA","258":"MZ","95":"MM","264":"NA","674":"NR","977":"NP","31":"NL","64":"NZ","505":"NI","227":"NE","234":"NG","47":"NO","968":"OM","92":"PK","680":"PW","507":"PA","675":"PG","595":"PY","51":"PE","63":"PH","48":"PL","351":"PT","974":"QA","40":"RO","250":"RW","1869":"KN","1758":"LC","1784":"VC","685":"WS","378":"SM","239":"ST","966":"SA","221":"SN","381":"RS","248":"SC","232":"SL","65":"SG","421":"SK","386":"SI","677":"SB","252":"SO","27":"ZA","34":"ES","94":"LK","249":"SD","597":"SR","268":"SZ","46":"SE","41":"CH","963":"SY","886":"TW","992":"TJ","255":"TZ","66":"TH","670":"TL","228":"TG","676":"TO","1868":"TT","216":"TN","90":"TR","993":"TM","688":"TV","1340":"VI","256":"UG","380":"UA","971":"AE","44":"GB","598":"UY","998":"UZ","678":"VU","379":"VA","58":"VE","84":"VN","967":"YE","260":"ZM","263":"ZW","383":"YK","211":"SS","86":"CN","970":"PS"}';
    /**
     * 获取数据
     * @param int $parent_id
     * @param int $show
     * @param array $field
     * @return mixed
     */
    public static function getItems($parent_id = 0, $show = 0, $field = ['*'])
    {
        $options = [['parent_id' => $parent_id]];
        if (!$show) {
            array_push($options, ['show' => 1]);
        }
        return CountryAreaRepository::get($options, $field);
    }

    /**
     * 根据国家ID获取所有省市地区
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public static function getAreaList($id)
    {
        return Cache::remember('country_' . $id, 43200, function () use ($id) {
            $states = self::getItems($id)->toArray();

            if ($states) {
                foreach ($states as $k => $state) {
                    $area = self::getItems($state['id'])->toArray();
                    $states[$k]['area'] = $area;
                }
                return $states;
            } else {
                throw new \Exception('something was wrong with your request');
            }
        });

    }
}