<?php


namespace app\fixtures;

use yii\test\ActiveFixture;

class UserCategoryFixture extends ActiveFixture
{
    public $tableName = 'user_category';
    public $dataFile = __DIR__ . '/data/userCategory.php';
}