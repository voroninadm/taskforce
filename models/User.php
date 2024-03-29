<?php

namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $name
 * @property string|null $birth_date
 * @property int $city_id
 * @property string|null $reg_date
 * @property int|null $avatar_file_id
 * @property string $email
 * @property string $password
 * @property string $phone
 * @property string $telegram
 * @property int|null $done_task
 * @property int|null $failed_task
 * @property float|null $rating
 * @property int $is_performer
 * @property int|null $is_private
 * @property int $is_busy
 *
 * @property File $avatarFile
 * @property Category[] $categories
 * @property City $city
 * @property Response[] $responses
 * @property Task[] $tasks
 * @property Task[] $tasks0
 * @property UserCategory[] $userCategories
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_FREE = 0;
    public const STATUS_BUSY = 1;
    public const ROLE_CUSTOMER = 0;
    public const ROLE_PERFORMER = 1;


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'city_id', 'email', 'password'], 'required'],
            [['birth_date', 'reg_date'], 'safe'],
            [
                ['city_id', 'avatar_file_id', 'done_task', 'failed_task', 'is_performer', 'is_private', 'is_busy'],
                'integer'
            ],
            [['rating'], 'number'],
            [['name', 'email', 'password', 'phone', 'telegram'], 'string', 'max' => 255],
            [['email'], 'unique'],
            [
                ['avatar_file_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => File::class,
                'targetAttribute' => ['avatar_file_id' => 'id']
            ],
            [
                ['city_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => City::class,
                'targetAttribute' => ['city_id' => 'id']
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Имя',
            'birth_date' => 'Дата рождения',
            'city_id' => 'ID города',
            'reg_date' => 'Дата регистрации',
            'avatar_file_id' => 'ID файла-аватарки',
            'email' => 'Email',
            'password' => 'Пароль',
            'phone' => 'Номер телефона',
            'telegram' => 'Telegram-аккаунт',
            'done_task' => 'Выполненные задания',
            'failed_task' => 'Проваленные задания',
            'rating' => 'Рейтинг',
            'is_performer' => 'Является ли заказчиком',
            'is_private' => 'Закрытый ли профиль',
            'is_busy' => 'Занят ли исполнитель',
        ];
    }

    /**
     * Gets query for [[AvatarFile]].
     *
     * @return ActiveQuery
     */
    public function getAvatarFile()
    {
        return $this->hasOne(File::class, ['id' => 'avatar_file_id']);
    }

    /**
     * Gets query for [[Categories]].
     *
     * @return ActiveQuery
     */
    public function getCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])->viaTable('user_category', ['user_id' => 'id']);
    }

    /**
     * Gets query for [[City]].
     *
     * @return ActiveQuery
     */
    public function getCity()
    {
        return $this->hasOne(City::class, ['id' => 'city_id']);
    }

    /**
     * Gets query for [[Responses]].
     *
     * @return ActiveQuery
     */
    public function getResponses()
    {
        return $this->hasMany(Response::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Tasks]].
     *
     * @return ActiveQuery
     */
    public function getTasks()
    {
        return $this->hasMany(Task::class, ['customer_id' => 'id']);
    }

    /**
     * Gets query for [[Tasks0]].
     *
     * @return ActiveQuery
     */
    public function getTasks0()
    {
        return $this->hasMany(Task::class, ['performer_id' => 'id']);
    }

    /**
     * Gets query for [[UserCategories]].
     *
     * @return ActiveQuery
     */
    public function getUserCategories()
    {
        return $this->hasMany(UserCategory::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for user-performer.
     *
     *
     * @return ActiveQuery
     */
    public function getReviews(): ActiveQuery
    {
        return $this->hasMany(Review::class, ['user_id' => 'id']);
    }

    /**
     * Calc user age
     * @return int years
     */
    public function getAge(): int
    {
        return date_diff(date_create($this->birth_date), date_create('now'))->y;
    }

    /**
     * Get user rating place
     * @return int
     */
    public function getRatingPlace(): int
    {
        return self::find()->where(['>', 'rating', $this->rating])->count() + 1;
    }

    public function validatePassword($password)
    {
        return \Yii::$app->security->validatePassword($password, $this->password);
    }

    public function getCountUserPerformerRating(): ActiveQuery
    {
        return $this->hasMany(Review::class, ['user_id' => 'id']);
    }

    public function getTasksWhereUserIsCustomer(): ActiveQuery
    {
        return $this->hasMany(Task::class, ['customer_id' => 'id']);
    }


    public function getTasksWhereUserIsPerformer(): ActiveQuery
    {
        return $this->hasMany(Task::class, ['performer_id' => 'id']);
    }

    //===identity interface methods
    public static function findIdentity($id)
    {
        return self::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // TODO: Implement findIdentityByAccessToken() method.
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        // TODO: Implement getAuthKey() method.
    }

    public function validateAuthKey($authKey)
    {
        // TODO: Implement validateAuthKey() method.
    }
}
