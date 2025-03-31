<?php

namespace app\records;

use app\audit\AuditBehavior;
use app\deliveryService\DeliveryService;
use app\deliveryService\DeliveryServiceHandler;
use app\helpers\EventProxy;
use app\helpers\Gender;
use app\referral\behaviors\SignupBehavior;
use app\referral\common\models\CodeHistory;
use app\shopfans\Shopfans;
use app\telegram\Events;
use app\telegram\TelegramSender;
use app\validators\ChangeCustomerEmailValidator;
use Shopfans\Api\UserApi;
use Shopober\Api\ShopoberUserApi;
use Yii;
use yii\base\Exception;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
use yii\filters\auth\HttpHeaderAuth;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "customer".
 *
 * @property int $id
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property string $middle_name
 * @property string $phone
 * @property string $password_hash
 * @property string $api_key
 * @property string $email_shopfans
 * @property string $password_shopfans
 * @property int $created_at
 * @property int $updated_at
 * @property null|int $locked_at
 * @property string $gender
 * @property null|int $birthday
 * @property null|string $referral_code
 * @property null|int $referrer_id
 * @property null|string $referrer_code
 *
 * @property UserApi $shopfans Shopfans API interface
 * @property ShopoberUserApi $shopober Shopober API interface
 * @property int $age customer age in years, 0 if birthday is undefined
 * @property string $ageName name of age, e.g. Teenager, Middle Adult, etc.
 *
 * Relations:
 * @property Order[] $orders
 * @property BasketItem[] $basketItems
 * @property PaymentCard[] $paymentCards
 * @property RefundedOrder[] $refundedOrder
 * @property Email[] $emails
 * @property CustomerSubscription[] $subscriptions
 * @property Favorite[] $favorites
 * @property Transaction[] $discounts
 * @property TransactionBatch[] $discountBatches
 * @property Customer[] $referrals
 * @property Customer|null $referrer
 * @property CustomerRecipient[]|null $innerRecipients
 * @property CustomerAddress[]|null $innerAddresses
 * @property CustomerToDelivery[]|null $linkToDelivery
 *
 * Calculated:
 * @property string $password
 * @property integer $innerBalance
 */
class Customer extends ActiveRecord implements IdentityInterface
{
    /**
     * Signup scenario
     */
    const SCENARIO_SIGNUP = 'signup';
    const SCENARIO_PROFILE = 'profile';
    const SCENARIO_SUBSCRIPTION = 'subscription';
    const SCENARIO_CHANGE_EMAIL = 'change_email';
    const SCENARIO_CHANGE_REFERRAL_CODE = 'change_referral_code';

    /**
     * @event CustomerNewPasswordEvent an event that is triggered once new password composed and assigned
     * at customer signup or password reset operation
     */
    const EVENT_NEW_PASSWORD = 'customer-new-password';

    /**
     * @event AfterInsertEvent an event that is triggered on customer signup
     */
    const EVENT_SIGNUP = 'customer-signup';

    /**
     * @event event that is triggered once new customer signup in mobile app
     *  on Ios platform
     */
    const EVENT_SIGNUP_IOS = 'customer-signup-ios';

    /**
     * @event event that is triggered once new customer signup in mobile app
     *  on Android platform
     */
    const EVENT_SIGNUP_ANDROID = 'customer-signup-android';

    /**
     * @event AfterUpdateEvent en event that is triggered on customer update
     */
    const EVENT_UPDATED = 'customer-updated';

    /**
     * @event event that is triggered once new customer signup in mobile app
     *  on Ios platform
     */
    const EVENT_UPDATED_IOS = 'customer-updated-ios';

    /**
     * @event event that is triggered once new customer signup in mobile app
     *  on Android platform
     */
    const EVENT_UPDATED_ANDROID = 'customer-updated-android';

    /**
     * @event event that is triggered once customer inner balance update
     */
    const EVENT_BALANCE_UPDATED = 'customer-balance-updated';

    /**
     * @event AfterSaveEvent an event that is triggered if customer locked
     */
    const EVENT_LOCKED = 'customer-locked';

    /**
     * @event AfterSaveEvent an event that is triggered if customer unlocked
     */
    const EVENT_UNLOCKED = 'customer-unlocked';

    const AGE_UNDEFINED = 'Undefined';
    const AGE_TEENAGER = 'Teenager (12-18)';
    const AGE_YOUTH = 'Youth (19-29)';
    const AGE_YOUNG_ADULT = 'Young Adult (30-44)';
    const AGE_MIDDLE_ADULT = 'Middle Adult (45-64)';
    const AGE_OLD_ADULT = 'Old Adult (65+)';
    protected static $sapiShopfans = [];
    protected static $sapiShopober = [];
    /**
     * @var string
     */
    public $password;
    /**
     * Old password used to confirm user authority to change password at profile scenarios.
     *
     * @var string
     */
    public $old_password;
    /**
     * @var boolean
     */
    public $is_admin;
    /**
     * @var string password composed for new customer at signup
     */
    protected $composedPassword;
    public $currencyRate = false;
    public $priceMarkupByMarketIds = false;
    /** @var bool Additional field to change email */
    public $newEmail = '';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'customer';
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
     * @return CustomerQuery
     */
    public static function find()
    {
        return new CustomerQuery(get_called_class());
    }

    /**
     * @inheritDoc
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        return parent::save($runValidation, $attributeNames);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        switch ($type) {
            case HttpHeaderAuth::class:
                //После регистрации пользователи не могут каталог увидеть
                Yii::$app->getDb()->enableSlaves = false;
                return self::find()->byApiKey($token)->active()->one();
            default:
                return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
//            'timestamp' => TimestampBehavior::class,
//            'audit' => [
//                'class' => AuditBehavior::class,
//                'operations' => [
//                    static::EVENT_AFTER_INSERT => 'New customer',
//                    static::EVENT_AFTER_UPDATE => 'Update profile',
//                    static::EVENT_LOCKED => 'Locked',
//                    static::EVENT_UNLOCKED => 'Unlocked',
//                ],
//            ],
//            'events' => [
//                'class' => EventProxy::class,
//                'map' => [
//                    static::EVENT_AFTER_INSERT => static::EVENT_SIGNUP,
//                    static::EVENT_AFTER_UPDATE => static::EVENT_UPDATED,
//                    static::EVENT_NEW_PASSWORD,
//                    static::EVENT_LOCKED,
//                    static::EVENT_UNLOCKED,
//                    static::EVENT_SIGNUP_IOS,
//                    static::EVENT_SIGNUP_ANDROID,
//                    static::EVENT_UPDATED_IOS,
//                    static::EVENT_BALANCE_UPDATED,
//                    static::EVENT_UPDATED_ANDROID
//                ],
//            ],
//            'referral' => SignupBehavior::class,
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_SIGNUP] = ['email', 'password', 'first_name', 'last_name', 'phone',
            'gender', 'birthday'];
        $scenarios[self::SCENARIO_PROFILE] = ['password', 'old_password', 'first_name', 'last_name',
            'middle_name', 'phone', 'gender', 'birthday'];
        $scenarios[self::SCENARIO_SUBSCRIPTION] = ['email'];
        $scenarios[self::SCENARIO_CHANGE_EMAIL] = ['email', 'newEmail'];
        $scenarios[self::SCENARIO_CHANGE_REFERRAL_CODE] = ['referral_code'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'required'],
            [['email', 'newEmail'], 'email', 'checkDNS' => true, 'enableIDN' => true],
            ['email', 'unique'],
            ['old_password', 'validateOldPassword', 'on' => self::SCENARIO_PROFILE],
            ['password', 'string', 'min' => 6, 'on' => self::SCENARIO_PROFILE],
            ['password', 'validateNewPassword', 'on' => self::SCENARIO_PROFILE],
            ['first_name', 'required', 'on' => self::SCENARIO_SIGNUP],
            ['first_name', 'string', 'min' => 2],
            ['last_name', 'required', 'on' => self::SCENARIO_SIGNUP],
            ['last_name', 'string', 'min' => 2],
            ['middle_name', 'string', 'min' => 2],
            ['phone', 'required', 'on' => self::SCENARIO_SIGNUP],
            [['email', 'newEmail'], 'required', 'on' => self::SCENARIO_CHANGE_EMAIL],
            [['newEmail'], ChangeCustomerEmailValidator::class, 'on' => self::SCENARIO_CHANGE_EMAIL],
            ['phone', 'string'],
            ['gender', 'string'],
            ['birthday', 'validateBirthday'],

            ['referral_code', 'required', 'on' => self::SCENARIO_CHANGE_REFERRAL_CODE],
            ['referral_code', 'string', 'on' => self::SCENARIO_CHANGE_REFERRAL_CODE],
            ['referral_code', 'trim', 'on' => self::SCENARIO_CHANGE_REFERRAL_CODE],
            ['referral_code', 'match', 'pattern' => '/^[a-z0-9\-\.\_]{5,25}$/', 'message' => Yii::t('referral', "Referral code must be from 5 to 25 english characters (a-z0-9-._)."), 'on' => self::SCENARIO_CHANGE_REFERRAL_CODE],
            ['referral_code', 'unique', 'targetClass' => Customer::class, 'targetAttribute' => 'referral_code', 'message' => Yii::t('referral', 'This code is already in use.'), 'on' => self::SCENARIO_CHANGE_REFERRAL_CODE],
        ];
    }

    public function validateBirthday($attribute)
    {
        // let use format YYYY-MM-DD
        if (preg_match('/^(1[98]|20)\d\d\-[01]\d\-[0123]\d$/', $this->$attribute)) {
            if (date('Y-m-d', @strtotime($this->$attribute)) === $this->$attribute) return true;
        } // and POSIX (UNIX timestamp)
        else if (ctype_digit($this->$attribute)) {
            if (strtotime($date = date('Y-m-d', $this->$attribute)) == $this->$attribute) {
                // convert POSIX to date
                $this->$attribute = $date;

                return true;
            }
        }

        $this->addError($attribute, strtr(Yii::t('yii', 'The format of {attribute} is invalid.'), [
            '{attribute}' => $attribute,
        ]));

        return false;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'email' => 'E-mail',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'middle_name' => 'Middle Name',
            'phone' => 'Phone',
            'password' => 'Password',
            'email_shopfans' => 'E-mail Shopfans',
            'password_shopfans' => 'Password Shopfans',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
            'gender' => 'Gender',
            'is_admin' => 'Is Admin',
            'birthday' => 'Birthday',
        ];
    }

    /**
     * @return OrderQuery|\yii\db\ActiveQuery
     */
    public function getOrders()
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    /**
     * @return BasketItemQuery|\yii\db\ActiveQuery
     */
    public function getBasketItems()
    {
        return $this->hasMany(BasketItem::class, ['customer_id' => 'id']);
    }

    /**
     * @return BasketItemQuery|\yii\db\ActiveQuery
     */
    public function getTransactionItems()
    {
        return $this->hasMany(Transaction::class, ['customer_id' => 'id']);
    }
    /**
     * @return BasketItemQuery|\yii\db\ActiveQuery
     */
    public function getTransactionBatchItems()
    {
        return $this->hasMany(TransactionBatch::class, ['customer_id' => 'id']);
    }

    /**
     * @return PaymentCardQuery|\yii\db\ActiveQuery
     */
    public function getPaymentCards()
    {
        return $this->hasMany(PaymentCard::class, ['customer_id' => 'id']);
    }

    /**
     * @return EmailQuery|\yii\db\ActiveQuery
     */
    public function getEmails()
    {
        return $this->hasMany(Email::class, ['customer_id' => 'id']);
    }

    /**
     * @return CustomerSignupMobile|\yii\db\ActiveQuery
     */
    public function getSignupMobile()
    {
        return $this->hasOne(CustomerSignupMobile::class, ['customer_id' => 'id']);
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return [
            'id',
            'email',
            'first_name',
            'last_name',
            'middle_name',
            'phone',
            'api_key',
            'gender',
            'is_admin' => function () {
                return (boolean)$this->getUser()->one();
            },
            'is_sp' => function () {
                return $this->isSp();
            },
            'birthday',
            'tests' => function () {
                $result = [
                    'type_id' => 1,
                    'enabled' => false,
                ];
                $customerTest = CustomerTest::find()->byCustomerId($this->id)->byTypeId(1)->one();
                if ($customerTest) {
                    $result = [
                        'type_id' => 1,
                        'enabled' => true,
                    ];
                }
                return $result;
            },
            'referral_code',
        ];
    }

    /**
     * @return UserQuery|\yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['customer_id' => 'id']);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return hash_hmac('md5', $this->email, 'sdljkhadfyknerbi7');
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (empty($this->password) && $insert && ($this->scenario == self::SCENARIO_SIGNUP || $this->scenario == self::SCENARIO_SUBSCRIPTION)) {
            $this->password = $this->composedPassword = $this->composePassword();
        }
        if ($this->password !== null && $this->password !== '') {
            $this->password_hash = Yii::$app->security->generatePasswordHash($this->password);
        }
        if ($insert || $this->password_hash != $this->getOldAttribute('password_hash')) {
            $this->api_key = Yii::$app->security->generateRandomString(32);
        }
//        if ($insert && !$this->gender && $this->first_name) {
//            $this->gender = Gender::getGenderByName([$this->first_name, $this->last_name]);
//        }
//        if (!$insert && $this->first_name != $this->getOldAttribute('first_name')) {
//            if ($this->gender == $this->getOldAttribute('gender')) {
//                $this->gender = Gender::getGenderByName([$this->first_name, $this->last_name]);
//            }
//        }
        if (!strtotime($this->birthday)) {
            $this->birthday = null;
        }

        // undefined gender in database is an empty string
        if ($this->gender !== Gender::MALE && $this->gender !== Gender::FEMALE) $this->gender = '';

        // All important validation is in the rules
        if($this->scenario === self::SCENARIO_CHANGE_EMAIL) {
            $this->handleChangeEmail();
        }

        if ($insert) {
            $this->email_shopfans = Shopfans::generateLogin();
            $this->password_shopfans = Shopfans::generatePassword();
        }

        return parent::beforeSave($insert);
    }

    /**
     * Composes random password with numeric and latin chars in lower case
     *
     * @return string
     */
    protected function composePassword()
    {
        return substr(base_convert(rand(PHP_INT_MAX / 3, PHP_INT_MAX), 10, 36), 1, 10);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // if customer already has shopfans account
        if (!$insert && $this->email_shopfans) {
            // обновляем данные о реальном телефоне и почте в шопфансе
            if (isset($changedAttributes['phone']) || isset($changedAttributes['email'])) {
                $this->shopfans->updateUserEmailPhone($this->email, $this->phone);
            }
            // check on changes of name, gender or birthday
            $changes = array_intersect_key($changedAttributes,
                ['first_name' => 1, 'last_name' => 1, 'gender' => 1, 'birthday' => 1]);
            // take actual value instead of old for each attribute
            foreach ($changes as $attribute => &$value) $value = $this->$attribute;
            // convert gender from text to numeric
            if (isset($changes['gender'])) $changes['gender'] = ($changes['gender'] == 'male') ? 0 : 1;
            if (isset($changes['birthday']) && !strtotime($changes['birthday'])) $changes['birthday'] = null;
            // update profile if there any changes in name, gender or birthday
            if ($changes) $this->shopfans->updateProfile($changes);
        }

        if (array_key_exists('locked_at', $changedAttributes)) {
            $this->trigger($this->locked_at ? self::EVENT_LOCKED : self::EVENT_UNLOCKED, new AfterSaveEvent([
                'changedAttributes' => $changedAttributes,
            ]));
        }

        // trigger event if new password composed and assigned to customer
        if ($this->composedPassword) {
            $this->trigger(self::EVENT_NEW_PASSWORD, new CustomerNewPasswordEvent([
                'newCustomer' => $insert,
                'newPassword' => $this->composedPassword,
            ]));

            $this->composedPassword = '';
        }
        // subscribe new customer
        if ($insert) {
            CustomerSubscription::newCustomerSubscribe($this->id);
            CustomerAddress::createDefaultAddress($this);
        }

        // save referral code history
        if (array_key_exists('referral_code', $changedAttributes)) {
            $codeHistory = new CodeHistory();
            $codeHistory->customer_id = $this->id;
            $codeHistory->old_code = $changedAttributes['referral_code'];
            $codeHistory->new_code = $this->referral_code;
            $codeHistory->save() or Yii::warning('Can not create referral code history.');
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRefundedOrder()
    {
        return $this->hasMany(RefundedOrder::class, ['customer_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getComment()
    {
        return $this->hasMany(CustomerComment::class, ['customer_id' => 'id']);
    }

    /**
     * Get customer subscriptions
     * @param bool $refreshFromMindbox - refresh subscriptions from mindbox
     * @return array
     */
    public function getSubscriptions(bool $refreshFromMindbox = false): array
    {
        /** @var CustomerSubscription  $subscription */
        $subscriptions = CustomerSubscription::find()->byCustomer($this->id)->one();
        return ($subscriptions) ? $subscriptions->getSubscriptionList($refreshFromMindbox) : [];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInnerAddresses()
    {
        return $this->hasMany(CustomerAddress::class, ['customer_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInnerRecipients()
    {
        return $this->hasMany(CustomerRecipient::class, ['customer_id' => 'id']);
    }

    /**
     * Reset user password to random one
     *
     * @return string composed password
     */
    public function resetPassword()
    {
        $composedPassword = $this->password = $this->composedPassword = $this->composePassword();
        $this->save();

        return $composedPassword;
    }

    public function validateOldPassword()
    {
        if (!$this->validatePassword($this->old_password)) {
            $this->addError('old_password', 'Укажите действующий пароль.');

            return false;
        }

        return true;
    }

    /**
     * @param string $password
     * @return bool
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function validateNewPassword()
    {
        if (empty($this->old_password)) {
            $this->addError('old_password', 'Укажите действующий пароль.');

            return false;
        }

        return true;
    }

    /**
     * Returns shopfans API ready to execute commands
     *
     * @return $this|UserApi
     * @throws \yii\base\InvalidConfigException
     */
    public function getShopfans()
    {
        if (!array_key_exists($this->id, static::$sapiShopfans)) {
            /** @var UserApi $api */
            $api = Yii::$container->get(UserApi::class);
            $email_shopfans = $this->email_shopfans;
            $password_shopfans = $this->password_shopfans;
            // кешируем по паре логин-пароль, на случай если пользователю вручную сменили пароль
            $profile = Yii::$app->cache->getOrSet(__FUNCTION__ . sha1($email_shopfans . $password_shopfans),
                function () use ($api, $email_shopfans, $password_shopfans) {
                    return $api->loginUser($email_shopfans, $password_shopfans);
                },
                86400);

            static::$sapiShopfans[$this->id] = [$api, @$profile['auth_token']];
        }

        list ($api, $token) = static::$sapiShopfans[$this->id];

        return $api && $token ? $api->auth($token, true) : $api;
    }

    /**
     * Returns shopober API ready to execute commands
     *
     * @return $this|ShopoberUserApi
     * @throws \yii\base\InvalidConfigException
     */
    public function getShopober()
    {
        if (!array_key_exists($this->id, static::$sapiShopober)) {
            /** @var ShopoberUserApi $api */
            $api = Yii::$container->get(ShopoberUserApi::class);
            $email_shopfans = $this->email_shopfans;
            $password_shopfans = $this->password_shopfans;
            // кешируем по паре логин-пароль, на случай если пользователю вручную сменили пароль
            $profile = Yii::$app->cache->getOrSet(__FUNCTION__ . sha1($email_shopfans . $password_shopfans),
                function () use ($api, $email_shopfans, $password_shopfans) {
                    return $api->loginUser($email_shopfans, $password_shopfans);
                },
                86400);

            static::$sapiShopober[$this->id] = [$api, @$profile['auth_token']];
        }

        list ($api, $token) = static::$sapiShopober[$this->id];

        return $api && $token ? $api->auth($token, true) : $api;
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
     * Gets a standard Customer name, e.g. last name + first_name.
     * @return string
     */
    public function getStandardName()
    {
        return implode(' ', [
            $this->last_name,
            $this->first_name,
        ]);
    }

    /**
     * Gets full Customer name.
     * @return string
     */
    public function getFullName()
    {
        return implode(' ', [
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ]);
    }

    /**
     * @return FavoriteQuery|\yii\db\ActiveQuery
     */
    public function getFavorites()
    {
        return $this->hasMany(Favorite::class, ['customer_id' => 'id']);
    }

    /**
     * @return ProductVariantQuery|\yii\db\ActiveQuery
     */
    public function getFavoriteProductVariants()
    {
        return $this->hasMany(ProductVariant::class, ['id' => 'product_variant_id'])
            ->viaTable('favorite', ['customer_id' => 'id']);
    }

    /**
     * Returns number of purchases.
     *
     * It counts purchases by orders with succesfull payments. It also includes returned orders/payments.
     */
    public function getPurchases()
    {
        return Order::find()->alias('o')->leftJoin(['p' => Payment::tableName()], 'o.payment_id = p.id')
            ->where('o.customer_id=:customer_id and p.status_code not in (:new, :failed)', [
                ':customer_id' => $this->id,
                ':new' => Payment::STATUS_NEW,
                ':failed' => Payment::STATUS_FAILED,
            ])
            ->count();
    }

    /**
     * Returns name of customer's age.
     *
     * The value could be:
     *
     * - Undefined, if birthday is undefined yet
     * - Teenager, for age up to 18
     * - Youth, for age from 19 to 29
     * - Young Adult, for age from 30 to 44
     * - Middle Adult, for age from 45 to 64
     * - Old Adult, for age 65 and above
     *
     * @return string
     */
    public function getAgeName()
    {
        $years = $this->getAge();

        if ($years >= 65) return static::AGE_OLD_ADULT;
        if ($years >= 45) return static::AGE_MIDDLE_ADULT;
        if ($years >= 30) return static::AGE_YOUNG_ADULT;
        if ($years >= 19) return static::AGE_YOUTH;
        if ($years >= 12) return static::AGE_TEENAGER;

        return static::AGE_UNDEFINED;
    }

    /**
     * Returns age of the customer in years or 0 if birthday is undefined
     *
     * @return int
     */
    public function getAge()
    {
        return $this->birthday
            ? (new \DateTime())->diff(new \DateTime($this->birthday))->y
            : 0;
    }

    /**
     * @return int
     */
    public function getInnerBalance()
    {
        $lastBalance = $this->getTransactions()->forLastBalance()->one();
        if (empty($lastBalance)) {
            return 0;
        }

        return $lastBalance->balance;
    }

    public function getInnerBalanceForOthersPayments()
    {
        $fullBalance = 0;
        $promoBalance = 0;
        $realBalance = 0;
        $otherPaymentBalance = 0;

        /**
         * @var TransactionBatch $batch
         */


        foreach ($this->getTransactionBatches()->all() as $batch) {
            $fullBalance += $batch->balance;
            if ($batch->is_promo) {
                $promoBalance += $batch->balance;
            } else {
                $realBalance += $batch->balance;
            }
            if ($batch->is_refund_from_other_payment) {
                $otherPaymentBalance += $batch->balance;
            }
        }
        return [
            'fullBalance' => $fullBalance,
            'promoBalance' => $promoBalance,
            'realBalance' => $realBalance,
            'otherPaymentBalance' => $otherPaymentBalance,
        ];
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTransactions()
    {
        return $this->hasMany(Transaction::class, ['customer_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTransactionBatches()
    {
        return $this->hasMany(TransactionBatch::class, ['customer_id' => 'id']);
    }

    /**
     * Возвращает флаг СП аккаунт или нет
     * @return bool
     */
    public function isSp()
    {
        $flag = true;
        if (strpos($this->email_shopfans, '@example.com') !== false) {
            $flag = false;
        }
        return (boolean)$flag;
    }

    /**
     * Устанавливает курс валюты исходя из того кто этот пользователь
     */
    public function setCurrencyRateAndMarkup()
    {
        $currencyRate = Yii::$app->currencyRate->getLatestPair();
        $this->currencyRate = $currencyRate->rate;
        //Раньше тут был для СПешниц - выпилили совсем
    }

    public function isAllowInnerPayment()
    {
        $count = InnerPaymentCustomer::find()->byCustomerId($this->id)->count();
        return $count > 0;
    }

    /**
     * Assign new E-mail to customer. Change previous e-mail with prefix "_old" if existed
     * @return void
     * @throws \Throwable
     */
    private function handleChangeEmail()
    {
        $this->email = $this->newEmail;
        if($foundedCustomer = self::find()->byEmail($this->newEmail)->one()) {
            $newEmailParts = explode('@', $this->newEmail);
            $newEmailParts[0] .= '_old';
            $foundedCustomer->email = implode('@', $newEmailParts);
            $foundedCustomer->save(false);
            $message =
                'Пользователь #' . $foundedCustomer->getId() . "\n" .
                'E-mail (old): ' . $this->newEmail . "\n" .
                'E-mail (new): ' . $foundedCustomer->email . "\n\n" .
                'Обновил: ' . Yii::$app->getUser()->getIdentity()->username . ' (' . (Yii::$app->getUser()->getIdentity()->email) . ')';

            TelegramSender::send('<strong>Изменен E-mail у пользователя</strong>'. "\n" . $message, Events::getYellowLogsChatId());
        }
    }

    /**
     * @return ActiveQuery
     */
    public function getReferrals(): ActiveQuery
    {
        return $this->hasMany(Customer::class, ['referrer_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getReferrer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'referrer_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getLinkToDelivery(): ActiveQuery
    {
        return $this->hasMany(CustomerToDelivery::class, ['customer_id' => 'id'])->indexBy('service_id');
    }

    /**
     * Synchronizes users in all services
     * @return void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \Exception
     */
    public function syncUsers()
    {
        foreach (DeliveryService::SERVICES as $serviceId => $serviceName) {
            if (!isset($this->linkToDelivery[$serviceId])) {
                $deliveryServiceHandler = \Yii::$container->get(DeliveryServiceHandler::class);
                $deliveryService = $deliveryServiceHandler->factory->createService($serviceName);
                $deliveryService->syncUser($this);
                if (!$this->getLinkToDelivery()->where(['service_id' => $serviceId])->exists()) {
                    throw new Exception(sprintf('Unable to create a user in service %s for customer %s', $serviceName, $this->id));
                }
            }
        }
    }

    /**
     * Synchronizes addresses in all services
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \Exception
     */
    public function syncAddresses()
    {
        foreach (DeliveryService::SERVICES as $serviceName) {
            $deliveryServiceHandler = \Yii::$container->get(DeliveryServiceHandler::class);
            $deliveryService = $deliveryServiceHandler->factory->createService($serviceName);
            $deliveryService->syncAddresses($this);
        }
    }

    /**
     * Synchronizes addresses in all services
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \Exception
     */
    public function syncRecipients()
    {
        foreach (DeliveryService::SERVICES as $serviceName) {
            $deliveryServiceHandler = \Yii::$container->get(DeliveryServiceHandler::class);
            $deliveryService = $deliveryServiceHandler->factory->createService($serviceName);
            $deliveryService->syncRecipients($this);
        }
    }

    /**
     * Creates a model from data from delivery service
     * @param array $address
     * @return CustomerAddress|null
     */
    public function createInnerAddress(array $address)
    {
        $customerAddress = new CustomerAddress([
            'customer_id' => $this->id,
            'full_name' => $address['full_name'],
            'phone' => $address['phone'],
            'phone2' => $address['phone2'],
            'user_address_type_id' => $address['user_address_type_id'],
            'address_name' => $address['address_name'],
            'country_id' => $address['country_id'],
            'spp_id' => $address['spp_id'],
            'city' => $address['city'],
            'street' => $address['street'],
            'street_number' => $address['street_number'],
            'slash_number' => $address['slash_number'],
        ]);

        if ($customerAddress->save()) {
            return $customerAddress;
        }

        return null;
    }

    /**
     * Creates a model from data from delivery service
     * @param array $recipient
     * @return CustomerRecipient
     */
    public function handleInnerRecipient(array $recipient): CustomerRecipient
    {
        $customerRecipient = CustomerRecipient::find()
            ->where([
                'customer_id' => $this->id,
                'hash' => $hash = $this->generateRecipientHash($recipient)
            ])
            ->one();

        if (!$customerRecipient) {
            $customerRecipient = new CustomerRecipient([
                'customer_id' => $this->id,
                'hash' => $hash
            ]);

            $customerRecipient->save();
        }

        return $customerRecipient;
    }

    /**
     * @param array $recipient
     * @return string
     */
    public function generateRecipientHash(array $recipient): string
    {
        $hashFields = [
            'passport_serial',
            'passport_number',
            'issued',
            'lastname',
            'firstname',
            'patronymic',
            'citizenship_id',
            'vatin',
            'phone',
            'address_zip',
            'address_country_id',
            'address_city',
            'address_street',
            'address_house',
        ];

        $recipientToHash = array_intersect_key($recipient, array_flip($hashFields));
        return hash('sha256', serialize($recipientToHash));
    }


}
