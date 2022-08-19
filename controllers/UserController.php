<?php


namespace app\controllers;


use app\models\Review;
use app\models\User;
use taskforce\classes\exceptions\NotFoundHttpException;
use yii\web\Controller;

class UserController extends Controller
{
    public function actionView(int $id): string
    {
        $user = User::findOne($id);
        if (!$user) {
            throw new NotFoundHttpException("Пользователь с ID=$id не найден");
        }

        $this->view->title = "Профайл пользователя $user->name";

        $reviews = Review::find()
            ->where(['user_id' => $id])
            ->all();

        return $this->render('view', [
            'user' => $user,
            'categories' => $user->categories,
            'reviews' => $reviews
        ]);
    }
}