<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use NiuGengYun\EasyTBK\Factory;

use NiuGengYun\EasyTBK\pinduoduo\request\DdkGoodsZsUnitUrlGenRequest;
use NiuGengYun\EasyTBK\taobao\request\TbkSpreadGetRequest;
use NiuGengYun\EasyTBK\taobao\request\TbkDgMaterialOptionalRequest;
use NiuGengYun\EasyTBK\jingdong\request\JdUnionPromotionCommonGetRequest;
//use NiuGengYun\EasyTBK\jingdong\request\JdUnionSellingPromotionGetRequest;

class TaoKe extends Controller
{
    
    /**
     * 多多客链接转换
     * @param String $source_url 要转换的原地址
     * @return String 转换后的短地址
     */
    protected function  getZsUnitUrl(String $source_url)
    {
        $pid = config('easytbk.pinduoduo.client_pid');
        
        $pdd = Factory::pinduoduo();
        $req = new DdkGoodsZsUnitUrlGenRequest();
        $req->setPid($pid);
        $req->setSourceUrl($source_url);
        //$req->setCustomParameters('');
        $res =  $pdd->execute($req);
        return empty($res['goods_zs_unit_generate_response']['short_url']) ? false : $res['goods_zs_unit_generate_response']['short_url'];
    }

    /**
     * 淘宝客地址转换
     * @param String $source_url 要转换的原地址
     * @return String 转换后的短地址
     */
    protected function TbkSpreadGet(String $source_url)
    {
        $client = Factory::taobao ();
        $req = new TbkSpreadGetRequest();
        $req->setRequests (json_encode(['url'=>$source_url]));
        $res = $client->execute ($req);
        //print_r($res);
        if ($res->results->tbk_spread[0]->err_msg != 'OK')
        {
            return $res->results->tbk_spread[0]->err_msg;
        }else{
            return $res->results->tbk_spread[0]->content;
        }
    }

    /**
     * 淘宝客，推广者物料搜索
     * @param String $q 搜索关键字
     * @return 搜索结果的第一个商品的淘宝客推广短链接
     */
    protected function TbkDgMaterialOptional(String $q)
    {
        $client = Factory::taobao ();
        $req = new TbkDgMaterialOptionalRequest();
        $req->setQ ($q);
        $adzoneId = config('easytbk.taobao.pid');
        $req->setAdzoneId($adzoneId);
        $res = $client->execute ($req);
        //print_r($res);
        if( isset($res->error_response) )
        {
            return $res->error_response->sub_msg;
        }else if(isset($res->code) and isset($res->sub_msg)){
            return $res->sub_msg;
        }else{
            if ($res->total_results >=1){
                $coupon_share_url = '没有优惠券';
                if ( isset($res->result_list->map_data[0]->coupon_share_url ))
                {
                    $coupon_share_url = $res->result_list->map_data[0]->coupon_share_url;
                    $coupon_share_url = $this->TbkSpreadGet('https:'. $coupon_share_url);
                }

                $long_url = $res->result_list->map_data[0]->url;
                $long_url = $this->TbkSpreadGet('https:'. $long_url);

                return array('long_url'=>$long_url,'coupon_share_url'=>$coupon_share_url);
                //return 
            }else{
                return "未搜索到结果";
            }
        }
    }

    /**
     * 京东京粉地址转换
     * @param String $source_url 要转换的原地址
     * @return String 转换后的短地址
     */
    protected function JDSpreadGet(String $source_url)
    {
        $client = Factory::jingdong();
        $req = new JdUnionPromotionCommonGetRequest;
        $req->setMaterialId ($source_url);
        $req->setSiteId('4100660886');
        $res = $client->execute ($req);
        //return $res;
        $res_json = json_decode($res['jd_union_open_promotion_common_get_response']['result']);
        if ($res_json->code == 200){
            return $res_json->data->clickURL;
            //return $this->JDSpreadGet_($res_json->data->clickURL);
        }else{
            //return false;
            return $res_json->message;
        }
    }

    /**
     * 京东京粉地址转换
     * @param String $source_url 要转换的原地址
     * @return String 转换后的短地址
     */
    /*
    protected function JDSpreadGet_(String $source_url)
    {
        $client = Factory::jingdong();
        $req = new JdUnionSellingPromotionGetRequest;
        $req->setMaterialId ($source_url);
        $req->setSiteId('4100660886');
        $res = $client->execute ($req);
        //print_r($res);
        $res_json = json_decode($res['jd_union_open_selling_promotion_get_response']['result']);
        if ($res_json->code == 200){
            return $res_json->data->shortURL;
        }else{
            //return false;
            return $res_json->message;
        }
    }
    */

    
    /**
     * 转换商品链接地址到淘客地址
     * 
     */
    public function genurl(Request $request)
    {
        //print_r($request->param);
        $kind = $request->kind;
        $source_url = $request->url;

        if ( empty($kind) or empty($source_url))
        {
            echo returnErr('输入为空');
            return;
        }
        
        if($kind == 'pdd')
        {            
            $short_url = $this->getZsUnitUrl($source_url);
            if ($short_url === false)
            {
                echo returnErr('地址转换失败');
            }else{
                $result = ['data'=>['url'=>$short_url]];
                echo json_encode($result);
            }
            
        }elseif($kind == 'tb'){

            $short_url = $this->TbkDgMaterialOptional($source_url);
            if(isset($short_url['long_url'])  and !empty($short_url['long_url']))
                $result = ['data'=>['url'=>$short_url['long_url'],'coupon_share_url'=>$short_url['coupon_share_url']]];
            else
                $result = ['data'=>['url'=>$short_url,'coupon_share_url'=>'没有优惠券']];

            echo json_encode($result);
        }else{
            $short_url = $this->JDSpreadGet($source_url);

            $result = ['data'=>['url'=>$short_url]];

            echo json_encode($result);

        }

        
    }

}
