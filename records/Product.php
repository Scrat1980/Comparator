<?php

namespace app\records;

use app\helpers\AlgoliaHelper;
use app\helpers\ProductReasonsHelper;
use app\markets\Market;
use app\recordsSphinx\ProductVariantSphinx;
use Yii;
use yii\base\InvalidConfigException;
use yii\caching\DbDependency;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use app\manticore\ManticoreHelper;
use yii\base\Event;

/**
 * Product Record
 *
 * DB fields:
 * @property int $id
 * @property int $market_id
 * @property string $remote_code
 * @property string $origin_name
 * @property string $origin_description
 * @property string $name
 * @property string $description
 * @property int $description_updated_at
 * @property int $default_variant_id
 * @property null|int $default_category_id
 * @property int $market_brand_id
 * @property null|int $brand_id
 * @property null|string $gender_code
 * @property int $first_parsing_id
 * @property int $last_parsing_id
 * @property bool $is_lost
 * @property int $is_block
 * @property int $is_custom_category
 * @property int $is_custom_gender
 * @property int $is_custom_facet
 * @property bool $disabled
 * @property int $created_at
 * @property int $losted_at
 * @property int $custom_category_at
 * @property int $custom_gender_at
 * @property int $blocked_at
 * @property int $updated_at
 * @property null|int $translated_at
 * @property null|int $approved_at
 * @property null|int $approved_by
 *
 * Relations:
 * @property Market $market
 * @property ProductData $data
 * @property ProductVariant[] $variants
 * @property ProductVariant[] $stockVariants
 * @property ProductVariant[] $apiVariants
 * @property ProductVariant $default
 * @property null|Category $defaultCategory
 * @property MarketBrand $marketBrand
 * @property null|Brand $brand
 * @property null|MarketGender $marketGender
 * @property null|string $genderLabel
 * @property Parsing $firstParsing
 * @property Parsing $lastParsing
 * @property Category[] $categories
 * @property MarketCategory[] $marketCategories
 * @property User $approver
 * @property DescriptionSnippet[] $descriptionSnippets
 * @property ProductMarketFacetValue[] $productMarketFacetValues
 * @property MarketFacetValue[] $marketFacetValues
 * @property ProductWebhook $webhook
 * @property ProductReasonsHelper $reasonsHelper
 * @property ProductBlock $blockedProduct
 * @property ProductTranslate $customTranslate
 * @property BrandWordForm $brandWordForm
 * @property ProductWeight $productWeightCtr
 * @property ProductWeight $productWeightLastNew
 * @property ProductAverageRating $productAverageRating
 * @property GoogleCatalogPrimary $googleCatalogPrimary
 * @property SizeChart[] $sizeCharts
 * @property Category[] $enabledCategories
 * @property DisabledMarketPercentCategory $disabledMarketPercent
 * @property DisabledProductLog[] $disabledLogs
 *
 * Calculated:
 * @property string $status
 */
class Product extends ActiveRecord {
    /**
     * Товар добавлен при парсинге, но еще не обработан (каталогизация и перевод).
     */
    const STATUS_NEW = 'new';
    /**
     * Товар переведен автоматически.
     */
    const STATUS_TRANSLATED = 'translated';
    /**
     * Товар одобрен к публикации.
     */
    const STATUS_APPROVED = 'approved';
    /**
     * Товар исчез из выдачи по результатам последнего парсинга.
     */
    const STATUS_LOST = 'lost';
    /**
     * Товар выключен.
     */
    const STATUS_DISABLED = 'disabled';

    /**
     * Товар не проверен.
     */
    const BLOCK_VAL_NOT_VERIFIED = 0;
    /**
     * Товар заблокирован.
     */
    const BLOCK_VAL_BLOCK = 1;
    /**
     * Товар проверен и не заблокирован.
     */
    const BLOCK_VAL_APPROVED = 2;


    const EVENT_LOST_PRODUCT = 'lost_product_event';
    const EVENT_DISABLED_PRODUCT = 'disabled_product_event';
    const EVENT_ENABLED_PRODUCT = 'enabled_product_event';
    const EVENT_PRODUCT_UPDATED = 'update_product_event';
    const EVENT_PRODUCT_CREATED = 'create_product_event';

    const PRODUCT_IS_LOST = 1;
    const PRODUCT_IS_NOT_LOST = 0;
    const PRODUCT_DISABLED = 1;
    const PRODUCT_ENABLED = 0;


    /**
     * Максимальная цена($) для витрины
     * ВНИМАНИЕ это значение вручную проставлено в config sphinx
     */
    const MAX_PRICE = 1500;

    /**
     * Общая наценка на товары
     * ВНИМАНИЕ это значение вручную проставлено в config sphinx
     */
    const GENERAL_MARGIN = 5.5;

    const CACHE_PRODUCT_MASS_EDIT = 'massEditProductIds';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'reasonsHelper' => ProductReasonsHelper::class,
        ];
    }

    /**
     * Defines the behaviors for the class.
     *
     * If you do it like this - you'll have problem with object serialization usually used in cache:
     * ```php
     * public function behaviors()
     * {
     *     return [
     *         'events' => [
     *             'class' => EventProxy::class,
     *             'map' => [
     *                 static::EVENT_LOST_PRODUCT,
     *                 static::EVENT_DISABLED_PRODUCT,
     *                 static::EVENT_ENABLED_PRODUCT,
     *                 static::EVENT_PRODUCT_CREATED,
     *                 static::EVENT_PRODUCT_UPDATED,
     *             ],
     *         ],
     *     ];
     * }
     * ```
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->on(self::EVENT_LOST_PRODUCT, function (Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
        $this->on(self::EVENT_DISABLED_PRODUCT, function (Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
        $this->on(self::EVENT_ENABLED_PRODUCT, function (Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
        $this->on(self::EVENT_PRODUCT_CREATED, function (Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
        $this->on(self::EVENT_PRODUCT_UPDATED, function (Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'product';
    }

    /**
     * @inheritdoc
     * @return ProductQuery
     */
    public static function find()
    {
        return (new ProductQuery(get_called_class()));
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'market_id' => 'Market',
            'market.name' => 'Market',
            'brand_id' => 'Brand',
            'brand.name' => 'Brand',
        ];
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @throws InvalidConfigException
     */
    public function afterSave($insert, $changedAttributes)
    {
        // return if nothing change
        if (!$changedAttributes) {
            return;
        }

        // new record event
        if ($insert) {
            $this->trigger(
                self::EVENT_PRODUCT_CREATED,
                new ProductEvent(['productId' => $this->id])
            );
            return;
        }

        // lost product event
        if ($this->is_lost && array_key_exists('is_lost', $changedAttributes)) {
            $this->trigger(
                self::EVENT_LOST_PRODUCT,
                new ProductLostEvent(['productId' => $this->id])
            );
        // update record event
        } else {
            $this->trigger(
                self::EVENT_PRODUCT_UPDATED,
                new ProductEvent(['productId' => $this->id])
            );
        }

        // lost product event
        if ($this->disabled && array_key_exists('disabled', $changedAttributes)) {
            $this->trigger(self::EVENT_DISABLED_PRODUCT);
        }
        // lost product event
        if ($this->disabled == 0 && array_key_exists('disabled', $changedAttributes)) {
            $this->trigger(self::EVENT_ENABLED_PRODUCT);
        }
        Yii::$app->cache->delete(ProductCache::CACHE_KEY . $this->id);

        // Отправляем событие на обновление данных в индексе только там, где были изменения
        if (
            Setting::enabled(Setting::ENABLED_MANTICORE_SYNC) &&
            $changedRtAttributes = array_intersect_key(ManticoreHelper::PRODUCT_UPDATE_ATTRIBUTES, $changedAttributes)
        ) {
            ManticoreHelper::onProductUpdated($this->id, $changedRtAttributes);
        }
    }

    public function beforeSave($insert)
    {
        //Because sometimes type bool and it is trigger lost event
        $this->is_lost = (int) $this->is_lost;
        $this->disabled = (int) $this->disabled;
        $this->is_custom_category = (int) $this->is_custom_category;
        $this->is_custom_gender = (int) $this->is_custom_gender;
        $this->is_custom_facet = (int) $this->is_custom_facet;

        if ($this->is_custom_category == 1) {
            $this->custom_category_at = time();
        }
        if ($this->is_custom_gender == 1) {
            $this->custom_gender_at = time();
        }

        if($this->customTranslate) {
            if($this->customTranslate->name) {
                unset($this->name);
            }
            if($this->customTranslate->description) {
                unset($this->description);
            }
        }

        return parent::beforeSave($insert);
    }

    /**
     * @return Market
     */
    public function getMarket()
    {
        return Yii::$app->markets->one($this->market_id);
    }

    /**
     * @return ProductVariantQuery|\yii\db\ActiveQuery
     */
    public function getDefault()
    {
        return $this->hasOne(ProductVariant::class, ['id' => 'default_variant_id']);
    }

    /**
     * @return null|string
     */
    public function getGenderLabel()
    {
        if ($this->gender_code === null) {
            return null;
        }
        return static::genderLabels()[$this->gender_code];
    }

    /**
     * @return array
     */
    public static function genderLabels()
    {
        return [
            'u' => 'Unisex',
            'ua' => 'Unisex Adults',
            'uk' => 'Unisex Kids',
            'm' => 'Man',
            'w' => 'Woman',
            'b' => 'Boy',
            'g' => 'Girl',
            'bb' => 'Baby Boy',
            'bg' => 'Baby Girl',
            'tb' => 'Toddler Boy',
            'tg' => 'Toddler Girl',
        ];
    }

    /**
     * @return CategoryQuery|\yii\db\ActiveQuery
     */
    public function getDefaultCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'default_category_id']);
    }

    /**
     * @return MarketBrandQuery|\yii\db\ActiveQuery
     */
    public function getMarketBrand()
    {
        return $this->hasOne(MarketBrand::class, ['id' => 'market_brand_id']);
    }

    /**
     * @return BrandQuery|\yii\db\ActiveQuery
     */
    public function getBrand()
    {
        return $this->hasOne(Brand::class, ['id' => 'brand_id']);
    }

    /**
     * @return MarketGenderQuery|\yii\db\ActiveQuery
     */
    public function getMarketGender()
    {
        return $this->hasOne(MarketGender::class, ['id' => 'market_gender_id'])
            ->viaTable('product_market_gender', ['product_id' => 'id']);
    }

    /**
     * @return ProductVariantQuery|\yii\db\ActiveQuery
     */
    public function getVariants()
    {
        return $this->hasMany(ProductVariant::class, ['product_id' => 'id']);
    }

    /**
     * @return ProductVariantQuery|\yii\db\ActiveQuery
     */
    public function getStockVariants()
    {
        return $this
            ->hasMany(ProductVariant::class, ['product_id' => 'id'])
            ->andWhere(['>' ,'stock_count', 0]);
    }

    /**
     * @return ProductVariant[]|ProductVariantQuery|array|\yii\db\ActiveQuery
     */
    public function getApiVariants()
    {
        if ($this->market_id === 29) {
            // iHerb - variants from product_variant_from_product (linked to product)
            $productIds = $this->hasMany(ProductVariantFromProduct::class, ['product_id' => 'id'])
                ->select('linked_product_id')
                ->column();
            $productIds[] = $this->id;
            $productVariants = ProductVariant::find()->byProductIds($productIds)->active()->all();
            $returnProductVariants = [];
            foreach ($productVariants as $productVariant) {
                if ($productVariant->product_id != $this->id) {
                    if ($productVariant->product->is_lost == Product::PRODUCT_IS_NOT_LOST) {
                        $returnProductVariants[] = $productVariant;
                    }
                } else {
                    $returnProductVariants[] = $productVariant;
                }
            }
            return $returnProductVariants;
        }
        return $this->hasMany(
            ProductVariant::class,
            ['product_id' => 'id']
        )
            ->with([
                'product',
                'product.brand',
                'product.disabledMarketPercent',
                'product.customTranslate',
                'marketSize',
                'marketSize.sizeBottomLength',
                'marketSize.sizeKidsAge',
                'marketSize.sizePlus',
                'marketSize.sizeKids',
                'marketSize.size',
                'image',
                'images',
                'marketColors',
                'marketColors.color',
                'russianSizeName',
            ])
            ->active();
    }

    /**
     * @return ParsingQuery|\yii\db\ActiveQuery
     */
    public function getFirstParsing()
    {
        return $this->hasOne(Parsing::class, ['id' => 'first_parsing_id']);
    }

    /**
     * @return ParsingQuery|\yii\db\ActiveQuery
     */
    public function getLastParsing()
    {
        return $this->hasOne(Parsing::class, ['id' => 'last_parsing_id']);
    }

    /**
     * @return MarketCategoryQuery|\yii\db\ActiveQuery
     */
    public function getMarketCategories()
    {
        return $this->hasMany(MarketCategory::class, ['id' => 'market_category_id'])
            ->viaTable('product_market_category', ['product_id' => 'id']);
    }

    /**
     * @return UserQuery|\yii\db\ActiveQuery
     */
    public function getApprover()
    {
        return $this->hasOne(User::class, ['id' => 'approved_by']);
    }

    /**
     * @return DescriptionSnippetQuery|\yii\db\ActiveQuery
     */
    public function getDescriptionSnippets()
    {
        return $this->hasMany(DescriptionSnippet::class, ['id' => 'snippet_id'])
            ->viaTable('product_description_snippet', ['product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getData()
    {
        return $this->hasOne(ProductData::class, ['product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProductAverageRating()
    {
        return $this->hasOne(ProductAverageRating::class, ['product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGoogleCatalogPrimary()
    {
        return $this->hasOne(GoogleCatalogPrimary::class, ['product_id' => 'id']);
    }

    /**
     * @return array
     */
    public function getFacets()
    {
        $resultFacets = [];
        $marketFacetValues = $this->getMarketFacetValues()->with(['facetValue', 'facetValue.facet'])->all();
        foreach ($marketFacetValues ?? [] as $marketFacetValue) {
            if (empty($facetValue = $marketFacetValue->facetValue)) {
                //Может быть пустым так как нет линковки между внутренними фасетами и магазинными
                continue;
            }
            $resultFacets[$facetValue->facet_id]['name_ru'] = $facetValue->facet->name_ru;
            $resultFacets[$facetValue->facet_id]['name_eng'] = $facetValue->facet->name_eng;
            $resultFacets[$facetValue->facet_id]['values'][$facetValue->id]['name_ru'] = $facetValue->name_ru;
            $resultFacets[$facetValue->facet_id]['values'][$facetValue->id]['name_eng'] = $facetValue->name_eng;
        }

        return $resultFacets;
    }

    /**
     * @return array
     */
    public function getFacetModels()
    {
        $resultFacets = [];
        $marketFacetValues = $this->getMarketFacetValues()->with(['facetValue', 'facetValue.facet'])->all();
        foreach ($marketFacetValues ?? [] as $marketFacetValue) {
            if (empty($facetValue = $marketFacetValue->facetValue)) {
                //Может быть пустым так как нет линковки между внутренними фасетами и магазинными
                continue;
            }
            $resultFacets[$facetValue->facet_id]['model'] = $facetValue->facet;
            $resultFacets[$facetValue->facet_id]['values'][$facetValue->id]['model'] = $facetValue;
        }

        return $resultFacets;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return [
            'id',
            'name' => function () {
                return $this->customTranslate->name ?? $this->name;
            },
            'origin_name',
            'is_custom_translated_name' => function () {
                return isset($this->customTranslate->name) ? 1 : 0;
            },
            'categories' => function () {
                return $this->enabledCategories;
            },
            'genderLabel',
            'description' => function () {
                return $this->customTranslate->description ?? $this->description;
            },
            'origin_description',
            'brand',
            'is_lost' => function () {
                if (!$this->enabledCategories || $this->market->isMarketDisabled()) {
                    return 1;
                }

                return $this->is_lost;
            },
            'facets',
            'default',
            'variants' => function () {
                return $this->apiVariants;
            },
            'qty_limit' => function () {
                //Получим из настроек лимит товаров по маркеетам и укажем значение для маркета этого товара
                if (Setting::enabled(Setting::QUANTITY_LIMIT)) {
                    $limits = Setting::value(Setting::QUANTITY_LIMIT);
                    if (isset($limits[$this->market_id])) {
                        $qtyLimit = $limits[$this->market_id];
                    } else if ($limits['all']) {
                        $qtyLimit = $limits['all'];
                    }
                    return $qtyLimit;
                }
                return 1;
            },
            'average_rating' => function () {
                if ($this->productAverageRating) {
                    return $this->productAverageRating->rating;
                }
                return null;
            },
        ];
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        if ($this->disabled) {
            return self::STATUS_DISABLED;
        }
        if ($this->is_lost) {
            return self::STATUS_LOST;
        }
        if ($this->approved_at) {
            return self::STATUS_APPROVED;
        }
        if ($this->translated_at) {
            return self::STATUS_TRANSLATED;
        }
        return self::STATUS_NEW;
    }

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function syncCategories(bool $resetDefaultCategory = false)
    {
        //Если для этого товара указано, что есть кастомные категории, то пропускаем
        //Это нужно для формирования, например, главного лендинга сайта - чтобы набрать в отдельную категорию товары для показа
        if ($this->is_custom_category) {
            return false;
        }

        $this->updateCategoryLinkByMarketCategories();
        $this->updateCategoryLinks();

        $this->setDefaultCategory($resetDefaultCategory);

        return true;
    }

    /**
     * Добавляет связи с категориями внутреннего каталога на основе связей product_market_category.
     *
     * ORDER BY NULL - Explain избавляет от лишней сортировки "Using filesort"
     * @throws Exception
     */
    private function updateCategoryLinks()
    {
        $selectPartOfSql = "
        SELECT pmc.product_id, mc.category_id
            FROM product_market_category pmc
            INNER JOIN market_category mc ON mc.id = pmc.market_category_id
            WHERE pmc.product_id = :id AND mc.category_id IS NOT NULL
            GROUP BY product_id, category_id
            ORDER BY NULL
        ";

        $sql = "
            INSERT IGNORE INTO product_category
            $selectPartOfSql
        ";
        self::getDb()->createCommand($sql, [':id' => $this->id])->execute();
    }

    /**
     * ВНИМАНИЕ именно этот метод лишает возможности вручную ставить категории товару в редактировании
     * при следующем парсинге они отлинкуются.
     *
     * Метод проверяет внутренние категории, к которым прилинкован product.
     * Если к product прилинкованы наши внутренние категории, которые не слинкованы с магазинными,
     * то такие категории отлинковывает
     * @throws InvalidConfigException
     */
    private function updateCategoryLinkByMarketCategories()
    {
        //Список внутренних категорий слинкованных
        $categoryLinksMarketCategory = [];
        foreach ($this->marketCategories as $marketCategory) {
            //Если магазинная категория имеет связь с внутренней категорией, то запишем в массив
            if ($marketCategory->category) {
                $categoryLinksMarketCategory[] = $marketCategory->category->id;
            }
        }

        if (count($categoryLinksMarketCategory) === 0) {
            //Если ни одна маркетовская категория не слинкована, то надо убрать информацию о линковках внутренних категорий
            foreach ($this->getCategories()->all() as $category) {
                $this->unlink('categories', $category, true);
            }
            //И удалить информацию о дефолтной категории
            $this->default_category_id = null;
            $this->save(false);
            return;
        }

        foreach ($this->getCategories()->all() as $category) {
            if (!in_array($category->id, $categoryLinksMarketCategory)) {
                $this->unlink('categories', $category, true);
            }
        }

        //Если мы разлинковали категорию которая была дефолтной то обнулим
        //Уберем пока такую возможность чтобы можно было делать самостоятельно типа кастомно дефолтную для наценок правильных
//        if (!in_array($this->default_category_id, $categoryLinksMarketCategory)) {
//            $this->default_category_id = null;
//            $this->save(false);
//        }
    }

    /**
     * Assign default category to product
     * Присваивает категорию по умолчанию для продукта:
     * первая найденная активная категория,
     * или первая найденная неактивная категория.
     * @param bool $flagReset - с этим флагом принудительно будет проставлен
     * @throws InvalidConfigException
     */
    public function setDefaultCategory($flagReset = false)
    {
        if ((!$flagReset && $this->default_category_id !== null) || !$this->getCategories()->count()) {
            return;
        }

        $categoryQuery = $this->getCategories();
        $disabledCategories = [];
        $currentDefaultCategory = $this->default_category_id;

        /* @var  Category $category */
        foreach ($categoryQuery->each() as $category) {

            if (in_array($category->id, Category::DEFAULT_CATEGORY_EXCEPTIONS) && $this->default_category_id !== $category->id) {
                $this->default_category_id = $category->id;
                $this->save();
                return true;
            }

            $parentL1 = $category->getParents()->select('id')->andWhere(['ns_depth' => 1])->asArray()->one() ?? [];
            $checkNewApproach = array_intersect(Category::DEFAULT_CATEGORIES_ALLOWED_L2, $parentL1);
            if ($checkNewApproach) {
                // Если уровень 2, 1, 0, и еще не назначена, то берем ее же
                if ($category->ns_depth <= 2 && $this->default_category_id !== $category->id) {
                    $this->default_category_id = $category->id;
                    $this->save();
                    return true;
                }

                $parentL2 = $category->getParents()->select('id')->andWhere(['ns_depth' => 2])->asArray()->one() ?? ['id' => null];
                // Если уровень 3 и более, то устанавливаем родителя
                if ($category->ns_depth >= 3 && $this->default_category_id !== $parentL2['id']) {
                    $this->default_category_id = $parentL2['id'] ?? $category->parent->id;
                    $this->save();
                }

                return;
            }

            if (!$category->getDisabledCategory()->exists()) {
                if ($this->default_category_id !== $category->id) {
                    $this->default_category_id = $category->id;
                    $this->save();
                }
                return;
            }

            $disabledCategories[] = $category->id;
        }

        if (!$disabledCategories) {
            return;
        }

        $this->default_category_id = $disabledCategories[0];
        $this->save();
    }

    /**
     * @return CategoryQuery|ActiveQuery
     * @throws InvalidConfigException
     */
    public function getCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])
            ->viaTable('product_category', ['product_id' => 'id']);
    }

    /**
     * @return CategoryQuery|ActiveQuery
     * @throws InvalidConfigException
     */
    public function getEnabledCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])
            ->viaTable('product_category', ['product_id' => 'id'])
            ->leftJoin('disabled_category', 'disabled_category.category_id = category.id')
            ->andWhere(['disabled_category.category_id' => null]);
    }

    /**
     * Возвращает true, если товар входит в указанную категорию. Проверяются все категории, в которые
     * входит товар. Проверяется вхождение в категорию с учетом всего дерева
     *
     * @param int|Category $category
     * @return bool
     */
    public function isInCategory($category): bool
    {
        if ($category instanceof Category) {
            $categoryId = $category->id;
        } elseif (is_int($category)) {
            $categoryId = $category;
        } else {
            throw new \Exception("Wrong parameter type: category");
        }
        foreach ($this->categories as $productCategory) {
            if ($productCategory->id == $categoryId) return true;
            foreach ($productCategory->parents as $parent) {
                if ($parent->id == $categoryId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProductMarketFacetValues()
    {
        return $this->hasMany(ProductMarketFacetValue::class, ['product_id' => 'id']);
    }

    /**
     * @return MarketFacetValueQuery|\yii\db\ActiveQuery
     */
    public function getMarketFacetValues()
    {
        return $this->hasMany(MarketFacetValue::class, ['id' => 'market_facet_value_id'])
            ->viaTable('product_market_facet_value', ['product_id' => 'id']);
    }

    /**
     * @return BrandWordFormQuery|\yii\db\ActiveQuery
     */
    public function getBrandWordForm()
    {
        return $this->hasMany(BrandWordForm::class, ['brand_id' => 'brand_id'])
            ->viaTable('brand_word_form', ['brand_id' => 'brand_id']);
    }

    /**
     * @return string|string[]|null
     */
    public function getUsmallUrl()
    {
        $url = strtolower($this->id . ' ' . $this->origin_name . ' ' . $this->brand->name);
        $url = preg_replace('/[^a-z0-9 \-]/', '', $url);
        $url = preg_replace('/\s+/', '-', $url);
        $url = preg_replace('/\-+/', '-', $url);
        $fUrl = getenv('FRONTEND_URL') ? getenv('FRONTEND_URL') : 'https://usmall.ru';
        $url = $fUrl . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . $url;

        return $url;
    }

    /**
     * @return string|string[]|null
     */
    public function getSmallUrl()
    {
        $url = strtolower($this->id . ' ' . $this->origin_name . ' ' . $this->brand->name);
        $url = preg_replace('/[^a-z0-9 \-]/', '', $url);
        $url = preg_replace('/\s+/', '-', $url);
        $url = preg_replace('/\-+/', '-', $url);
        $url = DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . $url;

        return $url;
    }

    /**
     * @return ProductWebhook|\yii\db\ActiveQuery
     */
    public function getWebhook()
    {
        return $this->hasOne(ProductWebhook::class, ['id' => 'id']);
    }

    /**
     * Переберем все варианты и если все варианты не продаются то и товар надо выключить
     * @param false $save
     * @return bool
     */
    public function checkIsLostByProductVariants($save = false)
    {
        $isLost = true;
        foreach ($this->variants as $productVariant) {
            if ($productVariant->stock_count > 0) {
                $isLost = false;
            }
        }
        if ($isLost) $this->markLost($save); else $this->markNotLost($save);

        return $isLost;
    }

    /**
     * Отметить что продукт пропал с витрины исходного магазина.
     *
     * @param bool $save
     * @return $this
     */
    public function markLost($save = true)
    {
        if (!$this->is_lost) {
            $this->is_lost = Product::PRODUCT_IS_LOST;
            $time = time();
            $this->losted_at = $time;
            $this->updated_at = $time;
            if ($save) {
                $this->save(false);
            }
        }
        return $this;
    }

    /**
     * Отметить что продукт присутсвует на витрине исходного магазина.
     *
     * @param bool $save
     * @return $this
     */
    public function markNotLost($save = true)
    {
        if ($this->is_lost) {
            $this->is_lost = Product::PRODUCT_IS_NOT_LOST;
            if ($save) {
                $time = time();
                $this->updated_at = $time;
                $this->save(false);
            }
        }
        return $this;
    }

    public function setDefaultVariant()
    {
        $default = $this->getVariants()->active()->first()->one();
        if (!empty($default)) {
            $this->default_variant_id = $default->id;
            $this->save(false);
        }
    }

    /**
     * Флаг находится ли товар на Витрине
     * @param $productId
     * @return bool
     */
    public static function inTheStore($productId)
    {
        $inTheStore = false;

        $sphinxSearch = ProductVariantSphinx::find()->where(['product_id' => $productId])->count();
        if ($sphinxSearch > 0) {
            $inTheStore = true;
        }

        return $inTheStore;
    }

    /**
     * Флаг находится ли товар в поиске Алголия
     * @param $productId
     * @return bool
     */
    public static function inAlgolia($productId)
    {
        if (Setting::disabled(Setting::ALGOLIA_DELETE_IS_LOST)) {
            return false;
        }
        $inAlgolia = false;

        $algoliaSearch = AlgoliaHelper::search($productId);
        if ($algoliaSearch && is_array($algoliaSearch) && count($algoliaSearch) > 0) {
            if (isset($algoliaSearch['hits']) && is_array($algoliaSearch['hits']) && count($algoliaSearch['hits']) > 0) {
                $inAlgolia = true;
            }
        }

        return $inAlgolia;
    }

    /**
     * Флаг, что товар принадлежит магазину который Выключен в настройках
     * @param $productId
     * @return bool
     */
    public static function isDisabledMarket($productId)
    {
        if (Setting::enabled(Setting::DISABLED_MARKET_IDS)) {
            $marketIds = Setting::value(Setting::DISABLED_MARKET_IDS);
            if (is_array($marketIds) && count($marketIds) > 0) {
                $product = self::findOne($productId);
                if (in_array($product->market_id, $marketIds)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Флаг, что товар принадлежит магазину который Выключен в настройках
     * @param Product $product
     * @return bool
     */
    public static function isDisabledMarketByProduct(Product $product): bool
    {
        if (Setting::enabled(Setting::DISABLED_MARKET_IDS)) {
            $marketIds = Setting::value(Setting::DISABLED_MARKET_IDS);
            if (is_array($marketIds) && count($marketIds) > 0 && in_array($product->market_id, $marketIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBlockedProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ProductBlock::class, ['entity_id' => 'id'])->andWhere(['type' => ProductBlock::TYPE_PRODUCT]);
    }

    /**
     * Returns list of all product categories in format of array
     * with two elements. The 0 index of each is MarketCategory and the 1 index is Category
     * which correspondingly relates with each other
     * if there is no relations so one of the indexes is null
     * @return array
     */
    public function getListOfAllKindOfCategories(): array
    {
        $allCategories = [];
        foreach ($this->marketCategories as $marketCategory) {
            $allCategories[] = [$marketCategory, $marketCategory->category];
        }
        $linkedCategoryIds = array_filter(array_unique(ArrayHelper::getColumn($this->marketCategories, 'category.id')));

        $categoriesWithCustoms = $this->getCategories()->where(['not in', 'id', $linkedCategoryIds]);

        foreach ($categoriesWithCustoms->all() as $category) {
            $allCategories[] = [null, $category];
        }

        return $allCategories;
    }

    /**
     * @return ActiveQuery
     */
    public function getCustomTranslate()
    {
        return $this->hasOne(ProductTranslate::class, ['product_id' => 'id']);
    }

    /**
     * @return SizeChartQuery
     */
    public function getSizeCharts(): SizeChartQuery
    {
        $sizeChartQuery = SizeChart::find()->where([
            'brand_id' => $this->brand_id,
            'gender_code' => $this->gender_code,
            'size_chart_type_id' => $this->defaultCategory->size_chart_type_id ?? null
        ]);

        $sizeChartDependencyQuery = clone $sizeChartQuery;

        $dependency = new DbDependency([
            'reusable' => true,
            'sql' => $sizeChartDependencyQuery->select(new Expression("MAX(updated_at)"))->createCommand()->getRawSql(),
        ]);

        return $sizeChartQuery->cache(1800, $dependency);
    }

    /**
     * Returns all category ids include parent ones
     * @param $productId
     * @return array|\yii\db\DataReader
     * @throws \yii\db\Exception
     */
    public static function getCategoriesByProductId($productId)
    {
        return Yii::$app->db->createCommand("
            SELECT c2.id, c2.name, c2.name_for_seo
            FROM product_variant pv
            INNER JOIN product p ON pv.product_id = p.id
            INNER JOIN product_category pc ON pc.product_id = p.id
            INNER JOIN category c ON c.id = pc.category_id
            INNER JOIN category c2 ON c2.ns_lkey <= c.ns_lkey
            AND c2.ns_rkey >= c.ns_rkey
            AND c2.ns_depth > 0
            WHERE c.id IS NOT NULL AND p.id = :productId
            GROUP BY  c2.id", [':productId' => $productId])->queryAll();

    }

    /**
     * @return ActiveQuery
     */
    public function getDisabledMarketPercent()
    {
        return $this->hasOne(
            DisabledMarketPercentCategory::class,
            [
                'category_id' => 'default_category_id',
                'market_id' => 'market_id'
            ]
        );
    }

    /**
     * @return ActiveQuery
     */
    public function getDisabledLogs()
    {
        return $this->hasMany(ProductTranslate::class, ['product_id' => 'id']);
    }

    /**
     * Whether to disable category margin amount
     * @return bool
     */
    public function isCategoryMarginDisabledForMarket(): bool
    {
        return Yii::$app->cache->getOrSet('PercentAmountDisabledByMarketId' . $this->default_category_id . '-' . $this->market_id . '1', function () {
            return is_object($this->disabledMarketPercent);
        }, 600);
    }
}
