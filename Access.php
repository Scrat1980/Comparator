<?php

namespace app;

use app\records\User;
use Yii;
use yii\rbac\CheckAccessInterface;

/**
 * Access
 */
class Access implements CheckAccessInterface
{
    // Site permissions
    const LOGIN = 'siteLogin';

    // Market permissions
    const MARKET_VIEW = 'marketView';
    const MARKET_PARSE = 'marketParse';
    const MARKET_APPLY_LOST = 'applyLost';

    // Parsing Queue related permissions
    const PARSING_QUEUE_VIEW = 'parsingQueueView';
    const PARSING_QUEUE_EDIT = 'parsingQueueEdit';

    // Dictionary permissions
    const DICT_VIEW = 'dictView';
    const DICT_EDIT = 'dictEdit';

    // Product permissions
    const PRODUCT_VIEW = 'productView';
    const PRODUCT_EDIT = 'productEdit';

    // User permissions
    const USER_VIEW = 'userView';
    const USER_EDIT = 'userEdit';

    // Log reader module permissions
    const LOG_VIEW = 'logView';

    // Carters parser module permissions
    const CARTERS_PARSER = 'cartersParser';

    // Order Redeem products
    const ORDER_VIEW = 'orderView';
    const ORDER_EDIT = 'order_edit';
    const ORDER_REDEEM = 'orderRedeem';
    const ORDER_CORRECT = 'orderCorrect';
    const ORDER_DEVELOPER = 'orderDeveloper';

    // Buyer permissions
    const BUYER_VIEW = 'buyerView';
    const BUYER_EDIT = 'buyerEdit';

    // Refunded permissions
    const REFUNDED_VIEW = 'refundedView';
    const REFUNDED_EDIT = 'refundedEdit';

    // Customer permissions
    const CUSTOMER_VIEW = 'customerView';

    // Payment permissions
    const PAYMENT_VIEW = 'paymentView';
    const PAYMENT_CREATE = 'paymentCreate';

    // Indexer permissions
    const INDEXER_VIEW = 'indexerView';

    // Email permissions
    const EMAIL_LIST_VIEW = 'emaiListlView';
    const EMAIL_VIEW = 'emailView';
    const EMAIL_TEMPLATE_LIST_VIEW = 'emailTemplateListView';
    const EMAIL_TEMPLATE_EDIT = 'emailTemplateEdit';

    // Editable attributes permission
    const CHANGE_EDITABLE_ATTRIBUTES = 'changeEditableAttributes';

    // Email parsing
    const EMAIL_PARSE_VIEW = 'emailParsingView';

    // Email parsing
    const EMAIL_PARSE_COST_VIEW = 'emailParseCostView';

    // Search Report
    const SEARCH_REPORT = 'searchReport';

    //Audit permissions
    const AUDIT_VIEW = 'auditView';

    //SEO Meta permissions
    const SEO_META_VIEW = 'seoMetaView';
    const SEO_META_EDIT = 'seoMetaEdit';

    //Mass Order History permission
    const MASS_ORDER_HISTORY_EDIT = 'massOrderHistoryEdit';

    //Request Replication permission
    const REQUEST_REPLICATION_VIEW = 'RequestReplicationView';

    //Discount permission
    const TRANSACTION_VIEW = 'transactionView';
    const TRANSACTION_EDIT = 'transactionEdit';

    //Setting
    const SETTING_VIEW = 'settingView';
    const SETTING_EDIT = 'settingEdit';

    //Promocode
    const PROMOCODE_VIEW = 'promocodeView';
    const PROMOCODE_EDIT = 'promocodeEdit';

    // web parsing
    const WEBPARSING = 'webParsingPullPush';

    const MARKET_ON_OFF = 'marketOnOff';

    const CUSTOMER_FEEDBACK = 'customerFeedback';

    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }

        switch ($user->role) {
            case User::ROLE_ADMIN:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::MARKET_VIEW,
                    self::MARKET_PARSE,
                    self::MARKET_APPLY_LOST,
                    self::DICT_VIEW,
                    self::DICT_EDIT,
                    self::PRODUCT_VIEW,
                    self::PRODUCT_EDIT,
                    self::USER_VIEW,
                    self::LOG_VIEW,
                    self::CARTERS_PARSER,
                    self::ORDER_VIEW,
                    self::ORDER_EDIT,
                    self::ORDER_REDEEM,
                    self::BUYER_VIEW,
                    self::BUYER_EDIT,
                    self::REFUNDED_VIEW,
                    self::REFUNDED_EDIT,
                    self::CUSTOMER_VIEW,
                    self::PAYMENT_VIEW,
                    self::PAYMENT_CREATE,
                    self::INDEXER_VIEW,
                    self::EMAIL_LIST_VIEW,
                    self::EMAIL_VIEW,
                    self::EMAIL_TEMPLATE_LIST_VIEW,
                    self::EMAIL_TEMPLATE_EDIT,
                    self::PARSING_QUEUE_VIEW,
                    self::PARSING_QUEUE_EDIT,
                    self::CHANGE_EDITABLE_ATTRIBUTES,
                    self::EMAIL_PARSE_VIEW,
                    self::EMAIL_PARSE_COST_VIEW,
                    self::SEARCH_REPORT,
                    self::AUDIT_VIEW,
                    self::SEO_META_VIEW,
                    self::SEO_META_EDIT,
                    self::TRANSACTION_VIEW,
                    self::TRANSACTION_EDIT,
                    self::PROMOCODE_VIEW,
                    self::PROMOCODE_EDIT,
                    self::WEBPARSING,
                ]);
            case User::ROLE_DEVELOPER:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::MARKET_VIEW,
                    self::MARKET_PARSE,
                    self::DICT_VIEW,
                    self::DICT_EDIT,
                    self::PRODUCT_VIEW,
                    self::PRODUCT_EDIT,
                    self::USER_VIEW,
                    self::USER_EDIT,
                    self::LOG_VIEW,
                    self::CARTERS_PARSER,
                    self::ORDER_VIEW,
                    self::ORDER_EDIT,
                    self::ORDER_REDEEM,
                    self::BUYER_VIEW,
                    self::BUYER_EDIT,
                    self::REFUNDED_VIEW,
                    self::REFUNDED_EDIT,
                    self::CUSTOMER_VIEW,
                    self::PAYMENT_VIEW,
                    self::PAYMENT_CREATE,
                    self::INDEXER_VIEW,
                    self::EMAIL_LIST_VIEW,
                    self::EMAIL_VIEW,
                    self::EMAIL_TEMPLATE_LIST_VIEW,
                    self::EMAIL_TEMPLATE_EDIT,
                    self::PARSING_QUEUE_VIEW,
                    self::PARSING_QUEUE_EDIT,
                    self::CHANGE_EDITABLE_ATTRIBUTES,
                    self::EMAIL_PARSE_VIEW,
                    self::EMAIL_PARSE_COST_VIEW,
                    self::SEARCH_REPORT,
                    self::AUDIT_VIEW,
                    self::MASS_ORDER_HISTORY_EDIT,
                    self::ORDER_CORRECT,
                    self::REQUEST_REPLICATION_VIEW,
                    self::TRANSACTION_VIEW,
                    self::TRANSACTION_EDIT,
                    self::SETTING_VIEW,
                    self::SETTING_EDIT,
                    self::MARKET_APPLY_LOST,
                    self::PROMOCODE_VIEW,
                    self::PROMOCODE_EDIT,
                    self::WEBPARSING,
                    self::ORDER_DEVELOPER,
                    self::SEO_META_VIEW,
                    self::SEO_META_EDIT,
                    self::MARKET_ON_OFF,
                    self::CUSTOMER_FEEDBACK,
                ]);
            case User::ROLE_ORDER_MANAGER_ADVANCED:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::MARKET_VIEW,
                    self::MARKET_PARSE,
                    self::DICT_VIEW,
                    self::DICT_EDIT,
                    self::PRODUCT_VIEW,
                    self::PRODUCT_EDIT,
                    self::USER_VIEW,
                    self::LOG_VIEW,
                    self::CARTERS_PARSER,
                    self::ORDER_VIEW,
                    self::ORDER_EDIT,
                    self::ORDER_REDEEM,
                    self::BUYER_VIEW,
                    self::BUYER_EDIT,
                    self::REFUNDED_VIEW,
                    self::REFUNDED_EDIT,
                    self::CUSTOMER_VIEW,
                    self::PAYMENT_VIEW,
                    self::PAYMENT_CREATE,
                    self::INDEXER_VIEW,
                    self::EMAIL_LIST_VIEW,
                    self::EMAIL_PARSE_COST_VIEW,
                    self::EMAIL_VIEW,
                    self::EMAIL_TEMPLATE_LIST_VIEW,
                    self::EMAIL_TEMPLATE_EDIT,
                    self::PARSING_QUEUE_VIEW,
                    self::PARSING_QUEUE_EDIT,
                    self::CHANGE_EDITABLE_ATTRIBUTES,
                    self::EMAIL_PARSE_VIEW,
                    self::SEARCH_REPORT,
                    self::AUDIT_VIEW,
                    self::ORDER_CORRECT,
                    self::PROMOCODE_VIEW,
                    self::PROMOCODE_EDIT,
                    self::TRANSACTION_VIEW,
                    self::TRANSACTION_EDIT,
                    self::MARKET_ON_OFF,
                ]);
            case User::ROLE_TRANSLATOR:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::MARKET_VIEW,
                    self::DICT_VIEW,
                    self::DICT_EDIT,
                    self::PRODUCT_VIEW,
                    self::PRODUCT_EDIT,
                ]);
            case User::ROLE_BUILDER:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::ORDER_VIEW,
                    self::ORDER_EDIT,
                    self::ORDER_REDEEM,
                    self::REFUNDED_VIEW,
                    self::REFUNDED_EDIT,
                    self::EMAIL_PARSE_VIEW,
                    self::WEBPARSING,
                ]);
            case User::ROLE_SEO:
                return in_array($permissionName, [
                    self::LOGIN,
                    self::SEO_META_VIEW,
                    self::SEO_META_EDIT,
                ]);
            default:
                return false;
        }
    }

    /**
     * @param int $id
     * @return User|null
     */
    protected function getUser($id)
    {
        if (!$id) {
            return null;
        }

        /** @var User[] $users */
        static $users = [];
        if (!array_key_exists($id, $users)) {
            if ($id == Yii::$app->user->id) {
                $users[$id] = Yii::$app->user->identity;
            } else {
                $users[$id] = User::findIdentity($id);
            }
        }
        return $users[$id];
    }
}
