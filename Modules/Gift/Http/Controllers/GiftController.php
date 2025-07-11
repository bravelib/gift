<?php

namespace Modules\Gift\Http\Controllers;

use App\Http\Constants\ApiStatus;
use App\Http\Helpers\RedisHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gift\Http\Repositories\GiftRepository;
use Modules\Gift\Models\Gift;
use Modules\Gift\Models\User;
use Throwable;
use Illuminate\Support\Facades\Log;

class GiftController extends Controller
{
    /**
     * 赠送幸运礼物(按组赠送礼物)
     * User:ytx
     * DateTime:2023/2/24 15:33
     */
    public function giveGroupGift(Request $request): JsonResponse
    {
        $uid          = $request->uid;
        $to_uid       = $request->input('to_uid', ''); # '1,2,3' 多用户(全麦)
        $to_uid       = array_filter(explode(',', $to_uid));
        $gift_id      = $request->input('gift_id', 0);
        $scene        = $request->input('scene', ''); # room|black|chat|help|bar_greet|bar_dating
        $scene_id     = (int)$request->input('scene_id', 0); # 房间id|小黑屋id,聊天会话id
        $number_group = (int)$request->input('number_group', 1); # 礼物组数，默认最小组数1组

        if (in_array($uid, $to_uid)) {
            return responseError([0, '不能送给自己']);
        }

        $giftkey       = 'luckgift' . $gift_id;
        $GiveGiftRedis = RedisHelper::get($giftkey);
        if ($GiveGiftRedis) {
            $giftInfo = json_decode($GiveGiftRedis, true);
        } else {
            $giftInfo = Gift::query()->where(['id' => $gift_id, 'status' => 1])->with('unit_price')->first();
            if (empty($giftInfo)) {
                return responseError([0, '所选礼物不存在，无法赠送']);
            }
            RedisHelper::set($giftkey, json_encode($giftInfo));
        }

        $to_uid_num = count($to_uid);
        $giftUnitCoin = $giftInfo['unit_price']['coin'];
        $giftTotalNeedCoin = bcmul($giftInfo['coin'], $number_group * $to_uid_num, 8);
        $eachUserGiftCoin = bcmul($giftInfo['coin'], $number_group, 8);

        $userCoinBalance = User::query()->where(['id' => $uid])->value('coin');
        if ($userCoinBalance < $giftTotalNeedCoin) {
            return responseError(ApiStatus::PLATFORM_COIN_INSUFFICIENT);
        }

        $results = [];

        try {
            foreach ($to_uid as $v) {
                GiftRepository::Factory()->doGiveGroupGift($uid, $v, $giftInfo, $scene, $scene_id, $number_group, $eachUserGiftCoin, $giftUnitCoin);

                // Amplified gift logic (10% chance)
                $amplified = false;
                $amplifiedValue = 0;
                if (rand(1, 100) <= 10) {
                    $amplifiedValue = $number_group * rand(2, 5);
                    $this->distributeAmplifiedReward($v, $amplifiedValue);
                    $this->showFloatingScreen($uid, $v, $amplifiedValue);
                    $amplified = true;
                }

                // Room and user ranking updates
                $this->updateRoomRanking($uid);
                $this->updateUserRanking($uid);
                $this->updateRoomConsumption();
                $this->distributeProfit($uid, $v, $gift_id, $number_group);
                $this->increaseCharm($uid);
                $this->levelUp($uid);
                $this->notifyRoom($uid, $v);

                $results[] = [
                    'to_user' => $v,
                    'status' => 'success',
                    'amplified' => $amplified,
                    'amplified_value' => $amplifiedValue
                ];
            }

            return response()->json([
                'message' => 'Gift sent successfully',
                'results' => $results
            ]);
        } catch (Throwable $e) {
            dp('礼物赠送出错,结束任务');
            return responseError([0, $e->getMessage()]);
        }
    }

    private function distributeAmplifiedReward($toUser, $value)
    {
        Log::info("Distributing amplified reward of $value to user $toUser");
    }

    private function showFloatingScreen($fromUser, $toUser, $value)
    {
        Log::info("Floating screen: $fromUser's gift to $toUser amplified to $value!");
    }

    private function updateRoomRanking($fromUser)
    {
        Log::info("Updating room ranking for user $fromUser");
    }

    private function updateUserRanking($fromUser)
    {
        Log::info("Updating user ranking for user $fromUser");
    }

    private function updateRoomConsumption()
    {
        Log::info("Updating room consumption stats");
    }

    private function distributeProfit($fromUser, $toUser, $giftId, $qty)
    {
        Log::info("Distributing profit of gift $giftId x $qty from $fromUser to $toUser");
    }

    private function increaseCharm($uid)
    {
        Log::info("Increasing charm value of user $uid");
    }

    private function levelUp($uid)
    {
        Log::info("Leveling up user $uid");
    }

    private function notifyRoom($fromUser, $toUser)
    {
        Log::info("Notification: $fromUser sent a gift to $toUser");
    }
}