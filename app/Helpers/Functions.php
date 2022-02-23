<?php

/**
 * 返回json 格式的错误信息
 */
function returnErr(String $err_msg)
{
    return json_encode(["errmsg"=>$err_msg]);
}