<?php


namespace app\controllers;


use app\services\LocationService;
use app\services\UserService;
use app\services\VkService;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\Response;

class VkController extends GuestController
{
    public function actions()
    {
        return [
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function onAuthSuccess($client): Response
    {
        $attributes = $client->getUserAttributes();
        $source = $client->getId();
        $sourceId = $attributes['id'];

        $vkService = new VkService($source, $sourceId);
        $userService = new UserService();
        $locationService = new LocationService();

        $vkAuthRecord = $vkService->getVkAuthRecord();

        // if user authorized via VK earlier
        if (!$vkAuthRecord) {
            $user = $vkAuthRecord->user;
            Yii::$app->user->login($user);

            if ($user->is_performer !== null) {
                return $this->redirect(['tasks/index']);
            } else {
                return $this->redirect(['profile/role']);
            }
        }

        $email = ArrayHelper::getValue($attributes, 'email');
        $existUser = $userService->getUserByEmail($email);

        // if user authorized via email not via VK earlier
        if (!is_null($existUser)) {
            $vkService->createVkAuthRecord($existUser->id);
            Yii::$app->user->login($existUser);

            return $this->redirect(['tasks/index']);
        }

        // if new user trying to register and authorize via VK
        $newUserData = [
            'name' => ArrayHelper::getValue($attributes, 'first_name') . ' ' . ArrayHelper::getValue(
                    $attributes,
                    'last_name'
                ),
            'email' => $email,
            'city' => ArrayHelper::getValue($attributes, 'city.title'),
            'password' => Yii::$app->security->generatePasswordHash(Yii::$app->security->generateRandomString(8)),
        ];

        if (!$locationService->isCityExistsInDB($newUserData['city'])) {
            throw new Exception('В ВК не указан город пользователя. Ввудите Ваш город в ВК или зарегистрируйтесь на сайте');
        }

        $newUser = $vkService->createUserFromVkData($newUserData);
        Yii::$app->user->login($newUser);

        return $this->redirect(['profile/role']);
    }
}