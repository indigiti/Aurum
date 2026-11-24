<?php
declare(strict_types=1);
use Aurum\ReviewMetrics;
use Aurum\RequestContext;
use Aurum\Accounts;
use Aurum\Identity;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
ini_set('session.use_strict_mode','1');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);session_start();
function review_send(array $v,int $status=200):never{http_response_code($status);echo json_encode($v,JSON_THROW_ON_ERROR);exit;}
if(!isset($_SESSION['uid']))review_send(['error'=>'Sign in required'],401);
$uid=(string)$_SESSION['uid'];$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){review_send(['enabled'=>aurum_review_enabled(),'provider'=>'OpenAI','requiresVerifiedEmail'=>true]);}
if($method!=='POST')review_send(['error'=>'Method not allowed'],405);
if(!hash_equals((string)($_SESSION['csrf']??''),$_SERVER['HTTP_X_CSRF_TOKEN']??''))review_send(['error'=>'CSRF rejected'],403);
session_write_close();
try{
    $store=aurum_storage();$identity=Accounts::authorize($store,$uid,aurum_identity_policy());
    if(!Identity::verified($identity))review_send(['error'=>'Verified email required for AI review'],403);
    if(!aurum_review_enabled())review_send(['error'=>'AI review is not configured'],503);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>1024)review_send(['error'=>'Request too large'],413);
    $body=json_decode((string)file_get_contents('php://input'),true,8,JSON_THROW_ON_ERROR);
    if(($body['consent']??false)!==true)review_send(['error'=>'Consent to send aggregate metrics required'],400);
    $metrics=$store->transaction(Accounts::scope($uid,RequestContext::requestedId()),static function(array &$s){if(!is_array($s['account']??null))throw new InvalidArgumentException('Account unavailable');return ReviewMetrics::metrics($s['account']);});
    if($metrics['closedTrades']<1)review_send(['error'=>'At least one closed simulated trade is required'],400);
    $day=gmdate('Y-m-d');
    foreach(['ai-rate-'.$uid=>3,'ai-rate-global'=>100] as $scope=>$limit){
        $ok=$store->transaction($scope,static function(array &$s)use($day,$limit){if(($s['day']??'')!==$day){$s['day']=$day;$s['count']=0;}if(($s['count']??0)>=$limit)return false;$s['count']=($s['count']??0)+1;return true;});
        if(!$ok)review_send(['error'=>'Daily AI review limit reached'],429);
    }
    review_send(['review'=>aurum_review($metrics),'provider'=>'OpenAI','mode'=>'educational-simulation']);
}catch(InvalidArgumentException|JsonException $e){review_send(['error'=>'Invalid review request'],400);}
catch(Throwable $e){error_log('Aurum review: '.get_class($e));review_send(['error'=>'AI review unavailable'],503);}
