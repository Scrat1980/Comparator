<?php

namespace app\records;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\filters\auth\HttpHeaderAuth;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $auth_key
 * @property string $password_hash
 * @property string $email
 * @property int $role
 * @property array $params
 * @property int $created_at
 * @property int $updated_at
 * @property null|int $locked_at
 * @property string $language
 *
 * @property string $password
 * @property string $roleName
 * @property Buyer[] $buyers
 */
class User extends ActiveRecord implements IdentityInterface
{
    const ROLE_ADMIN = 1;
    const ROLE_TRANSLATOR = 2;
    const ROLE_BUILDER = 3;
    const ROLE_DEVELOPER = 4;
    const ROLE_SEO = 5;
    const ROLE_ORDER_MANAGER_ADVANCED = 6;

    //id user используется для комментирования и выполения действий
    //при автоматическом разборе писем
    const BOT_ACCOUNT = 31;

    /**
     * Email of special user used to identify regular web interface
     */
    const WEB_EMAIL = 'web@usmall.ru';

    /**
     * Email of special user used to identify API interface
     */
    const API_EMAIL = 'api@usmall.ru';

    /**
     * Email of special user used to identify command line interface
     */
    const CLI_EMAIL = 'cli@usmall.ru';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * @inheritdoc
     * @return UserQuery
     */
    public static function find()
    {
        return new UserQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::find()->byId($id)->active()->one();
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        switch ($type) {
            case HttpHeaderAuth::class:
                return static::find()->byAuthKey($token)->active()->one();
            default:
                return null;
        }
    }

    /**
     * @return array
     */
    public static function roleNames()
    {
        return [
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_TRANSLATOR => 'Translator',
            self::ROLE_BUILDER => 'Builder',
            self::ROLE_DEVELOPER => 'Developer',
            self::ROLE_SEO => 'SEO',
            self::ROLE_ORDER_MANAGER_ADVANCED => 'Order manager (advanced)',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * @inheritdoc
     */
    public function getLogLastId($target)
    {
        return $this->params['logreader'][$target] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function saveLogLastId($target, $id)
    {
        $params = $this->params ?: [];
        $params['logreader'][$target] = $id;
        $this->params = $params;
        $this->save(false);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique'],

            ['username', 'required'],
            ['username', 'string'],

            ['password', 'string'],

            ['customer_id', 'integer'],

            ['role', 'required'],
            ['language', 'string'],
            ['role', 'in', 'range' => array_keys(static::roleNames())],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Name',
            'email' => 'E-Mail',
            'customer_id' => 'Customer ID',
            'roleName' => 'Role',
            'language' => 'Language',
        ];
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return '';
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        if ($password !== null && $password !== '') {
            $this->password_hash = Yii::$app->security->generatePasswordHash($password);
        }
    }

    /**
     * @param string $password
     * @return bool
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * @return string
     */
    public function getRoleName()
    {
        return static::roleNames()[$this->role];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->generateAuthKey();
        }
        return parent::beforeSave($insert);
    }

    /**
     * @return $this
     */
    public function lock()
    {
        $this->locked_at = time();
        return $this;
    }

    /**
     * @return $this
     */
    public function unlock()
    {
        $this->locked_at = null;
        return $this;
    }

    /**
     * @return BuyerQuery|\yii\db\ActiveQuery
     */
    public function getBuyers()
    {
        return $this->hasMany(Buyer::class, ['id' => 'buyer_id'])
            ->viaTable('user_buyer', ['user_id' => 'id']);
    }

    /**
     * @return CustomerQuery|\yii\db\ActiveQuery
     */
    public function getCustomer()
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }
}
