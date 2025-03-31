<?php

namespace app\records;

use app\api\filters\ProductVariantSphinxFilter;
use app\api\jobs\ProductVariantMainRTJob;
use app\helpers\CryptoHelper;
use app\helpers\DutyFeeHelper;
use app\helpers\GoogleRecommendHelper;
use app\helpers\PercentCategoryBrandHelper;
use app\helpers\PromocodeHelper;
use app\markets\kohls\Market;
use app\recordsSphinx\ProductVariantSphinx;
use app\telegram\TelegramChatIdSetting;
use app\telegram\TelegramQueueJob;
use app\telegram\TelegramSender;
use Yii;
use yii\caching\DbDependency;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\sphinx\ActiveDataProvider;

/**
 * Product Variant Record
 *
 * DB fields:
 * @property int $id
 * @property int $product_id
 * @property string $remote_code
 * @property string $upc
 * @property string $remote_url
 * @property int $image_id
 * @property string $description
 * @property string|null $origin_color DEPRECATED //Нужно для правильного формирования данных для Sphinx
 * @property string|null $origin_size
 * @property int|null $market_size_id
 * @property float $price
 * @property float $price_full
 * @property float $discount
 * @property int $stock_count
 * @property int $created_at
 * @property int $losted_at
 * @property int $updated_at
 *
 * Relations:
 * @property Product $product
 * @property ProductVariantData $data
 * @property PriceHistory[] $priceHistory
 * @property MarketColor[] $marketColors
 * @property Image[] $images
 * @property Image $image
 * @property MarketSize $marketSize
 * @property SizeChart $sizeChart
 * @property RussianSizeName $russianSizeName
 * @property ProductVariantAdditionalUpc $additionalUpc
 * @property ProductVariantRmssku $rmsskus
 * @property ProductVariantCustomPrice $customPrice
 * @property DisabledProductVariant $disabledProductVariant
 * @property ProductVariantExpirationDate $productVariantExpirationDate
 * @property ProductVariantPrice $productVariantPrice
 */
class ProductVariant extends ActiveRecord
{
    /**
     * @event AfterUpdateEvent en event that is triggered on product variant update
     */
    const EVENT_UPDATED = 'variant_updated_event';
    /**
     * @event event that is triggered on variant lost
     */
    const EVENT_LOST_VARIANT = 'variant_lost_event';

    public function init()
    {
        parent::init();

        $this->on(self::EVENT_LOST_VARIANT, function (\yii\base\Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
        $this->on(self::EVENT_UPDATED, function (\yii\base\Event $event) {
            Yii::$app->trigger($event->name, $event);
        });
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'product_variant';
    }

    /**
     * @inheritdoc
     * @return ProductVariantQuery
     */
    public static function find()
    {
        return new ProductVariantQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'remote_url' => 'URL',
            'upc' => 'UPC',
            'description' => 'description',
            'origin_color' => 'Color',
            'origin_size' => 'Size',
            'price' => 'Price',
            'stock_count' => 'Stock Count',
        ];
    }

    /**
     * @param $insert
     * @param $changedAttributes
     * @return void
     */
    public function afterSave($insert, $changedAttributes)
    {
        // return if nothing change or new record
        if (!$changedAttributes || $insert) {
            return;
        }
        // update record event
        $this->trigger(self::EVENT_UPDATED);
        // lost product variant event
        if (!$this->stock_count && array_key_exists('stock_count', $changedAttributes)) {
            $this->trigger(self::EVENT_LOST_VARIANT, new ProductVariantLostEvent([
                'productVariantId' => $this->id,
            ]));
            $this->product->checkIsLostByProductVariants(true);
        }

        //TODO выпилить трекинг #21423, больше не актуально
        /*if ($this->product->market_id == (new Market())->getId()) {
            $this->checkTracker();
        }*/
    }

    /**
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function afterDelete()
    {
        /*Yii::$app->lowQueue->push(new ProductVariantMainRTJob([
            'productVariantId' => $this->id,
            'type' => ProductVariantMainRTJob::PV_DELETE,
        ]));*/
    }

    /**
     * @return ProductQuery|\yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getData()
    {
        return $this->hasOne(ProductVariantData::class, ['product_variant_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPriceHistory()
    {
        return $this->hasMany(PriceHistory::class, ['product_variant_id' => 'id']);
    }

    /**
     * @return MarketColorQuery|\yii\db\ActiveQuery
     */
    public function getMarketColors()
    {
        return $this->hasMany(MarketColor::class, ['id' => 'market_color_id'])
            ->viaTable('product_variant_market_color', ['product_variant_id' => 'id']);
    }

    /**
     * @return MarketSizeQuery \yii\db\ActiveQuery
     */
    public function getMarketSize()
    {
        return $this->hasOne(MarketSize::class, ['id' => 'market_size_id']);
    }

    /**
     * @return MarketSize2Query|\yii\db\ActiveQuery
     */
    public function getMarketSize2()
    {
        return $this->hasMany(MarketSize2::class, ['id' => 'market_size_id'])
            ->viaTable('product_variant_market_size2', ['product_variant_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     * @throws \yii\base\InvalidConfigException
     */
    public function getRussianSizeName()
    {
        return $this->hasOne(RussianSizeName::class, ['id' => 'russian_size_name_id'])
            ->viaTable('product_variant_russian_size_name', ['product_variant_id' => 'id']);
    }

    /**
     * @return ImageQuery|\yii\db\ActiveQuery
     */
    public function getImages()
    {
        return $this->hasMany(Image::class, ['id' => 'image_id'])
            ->viaTable('product_variant_image', ['product_variant_id' => 'id']);
    }

    /**
     * @return ImageQuery|\yii\db\ActiveQuery
     */
    public function getImage()
    {
        return $this->hasOne(Image::class, ['id' => 'image_id']);
    }

    /**
     * @return ImageVariant|Image|null
     */
    public function getImageVariantOrOriginal($width, $height)
    {
        if (!is_int($width) || (int)$width < 0) {
            $width = 0;
        }

        if (!is_int($height) || (int)$height < 0) {
            $height = 0;
        }

        $image = $this->hasOne(ImageVariant::class, ['image_id' => 'image_id'])
            ->bySize((int)$width, (int)$height)
            ->one();

        if (!$image) {
            return $this->getImage()->one();
        }

        return $image;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAdditionalUpc()
    {
        return $this->hasOne(ProductVariantAdditionalUpc::class, ['product_variant_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCustomPrice()
    {
        return $this->hasOne(ProductVariantCustomPrice::class, ['product_variant_id' => 'id']);
    }


    /**
     * Продажная цена варианта
     * @return float
     */
    public function getSellingPrice($forDiscount = false)
    {
        if (Setting::enabled(Setting::PRODUCT_VARIANT_CUSTOM_PRICE)) {
            //Проверим установлена ли кастомная цена для товара
            if ($customPrice = $this->customPrice) {
                return PromocodeHelper::executePrice($this, $customPrice->price, $forDiscount);
            }
        }

        static $currencyRate;
        if (!$currencyRate) {
            $currencyRate = Yii::$app->currencyRate->getLatestPair() ?? false;
        }
        $rateByMarket = false;
        //Если есть курс валюты для магазина
        if (Setting::enabled(Setting::RATE_BY_MARKET)) {
            $valueRateByMarket = Setting::value(Setting::RATE_BY_MARKET);
            if (isset($valueRateByMarket[$this->product->market_id])) {
                $rateByMarket = $valueRateByMarket[$this->product->market_id];
            }
        }
        if ($this->product->market_id == 28 && Setting::enabled(Setting::GIFT_CARD_CURRENCY_RATE)) {
            $rate = Setting::value(Setting::GIFT_CARD_CURRENCY_RATE);
        } elseif ($rateByMarket !== false) {
            $rate = $rateByMarket;
        } else {
            //Курс валюты для всех пользователей
            $rate = $currencyRate->rate;
        }
        //Раньше тут был для СПешниц - выпилили совсем

        //Если это обычный пользователь или неавторизованный, то логика простая
        $price = round(($this->getPriceCost() * $rate) + 10/2,-1);
        if(!$forDiscount) {
            return $price;
        }

        return PromocodeHelper::executePrice($this, $price, $forDiscount);
    }

    /**
     * Продажная цена варианта
     * @return float
     */
    public function getSellingPriceFull()
    {
        //Если у нас нет полной цены по которой мы считаем скидку(мнимую) то пропускаем
        if (!$this->price_full || empty($this->price_full)) {
            return null;
        }

        static $currencyRate;
        if (!$currencyRate) {
            $currencyRate = Yii::$app->currencyRate->getLatestPair() ?? false;
        }
        $rateByMarket = false;
        //Если есть курс валюты для магазина
        if (Setting::enabled(Setting::RATE_BY_MARKET)) {
            $valueRateByMarket = Setting::value(Setting::RATE_BY_MARKET);
            if (isset($valueRateByMarket[$this->product->market_id])) {
                $rateByMarket = $valueRateByMarket[$this->product->market_id];
            }
        }
        if ($this->product->market_id == 28 && Setting::enabled(Setting::GIFT_CARD_CURRENCY_RATE)) {
            $rate = Setting::value(Setting::GIFT_CARD_CURRENCY_RATE);
        } elseif ($rateByMarket !== false) {
            $rate = $rateByMarket;
        } else {
            //Курс валюты для всех пользователей
            $rate = $currencyRate->rate;
        }
        //Раньше тут был для СПешниц - выпилили совсем

        //Если это обычный пользователь или неавторизованный, то логика простая
        $price = round(($this->getPriceFullCost() * $rate) + 10/2,-1);

        return $price;
    }

    /**
     * Расчет цены со всеми наценками в исходной валюте USD
     * @return float|int
     */
    public function getPriceCost()
    {
        $feeAmount = 0;
        $percentAmount = 0;
        //Добавление наценки по категории
        if ($this->product->default_category_id && $this->product->defaultCategory) {
            $feeAmount = $this->product->defaultCategory->fee_amount;
            if (!$this->product->isCategoryMarginDisabledForMarket()) {
                $percentAmount = $this->product->defaultCategory->percent_amount;
            }
        }
        $percent = 0;
        if ($this->product->defaultCategory) {
            $defaultCategoryId = $this->product->defaultCategory->id;
            $brandId = $this->product->brand_id;
            $percentCategoryBrand = PercentCategoryBrandHelper::execute($this->product->market_id, $defaultCategoryId, $brandId);
            //Добавление наценки по бренду
            if ($percentCategoryBrand) {
                $percent = $percentCategoryBrand->percent;
            }
        }

        $marketPercentAmount = MarketList::getPercentAmountByMarketId($this->product);

        $price = $this->price * (1 + ($percent / 100) + ($percentAmount / 100) + (Product::GENERAL_MARGIN / 100) + ($marketPercentAmount / 100)) + $feeAmount;
        //Добавление наценки по диапазонам цен
        $priceGroup = PriceGroup::findOneByPriceUsd($price);
        if (!empty($priceGroup) && $priceGroup->percent > 0) {
            $price = $price * (1 + $priceGroup->percent /100);
        }

        return $price;
    }

    /**
     * @return float|int
     */
    public function getPriceFullCost()
    {
        if (!$this->price_full || empty($this->price_full)) {
            return null;
        }

        $feeAmount = 0;
        $percentAmount = 0;
        if ($this->product->default_category_id && $this->product->defaultCategory) {
            $feeAmount = $this->product->defaultCategory->fee_amount;
            if (!$this->product->isCategoryMarginDisabledForMarket()) {
                $percentAmount = $this->product->defaultCategory->percent_amount;
            }
        }
        $percent = 0;
        if ($this->product->defaultCategory) {
            $defaultCategoryId = $this->product->defaultCategory->id;
            $brandId = $this->product->brand_id;
            $percentCategoryBrand = PercentCategoryBrandHelper::execute($this->product->market_id, $defaultCategoryId, $brandId);
            if ($percentCategoryBrand) {
                $percent = $percentCategoryBrand->percent;
            }
        }
        $marketPercentAmount = MarketList::getPercentAmountByMarketId($this->product);

        $priceFull = $this->price_full * (1 + ($percent / 100) + ($percentAmount / 100) + (Product::GENERAL_MARGIN / 100) + ($marketPercentAmount / 100)) + $feeAmount;
        $priceGroup = PriceGroup::findOneByPriceUsd($priceFull);
        if (!empty($priceGroup) && $priceGroup->percent > 0) {
            $priceFull = $priceFull * (1 + $priceGroup->percent /100);
        }

        return $priceFull;
    }

    /**
     * Детальная информация для СП аккаунтов по расчету цены для них
     * @return float[]
     */
    public function getPriceDetailSP()
    {
        //Раньше тут был для СПешниц - выпилили совсем

        return [];
    }

    /**
     * @return string
     */
    public function getNameForDeclaration()
    {
        $nameForDeclaration = '';
        try {
            if ($this->product->categories[0]->name_female_ru == '') {
                if ($this->product->categories[0]->name_male_ru == '') {
                    if ($this->product->categories[0]->name_girl_ru == '') {
                        if ($this->product->categories[0]->name_boy_ru == '') {
                            if ($this->product->categories[0]->name == '') {
                                $nameForDeclaration = $this->product->categories[0]->name;
                                $message = sprintf('!!!ALARM!!! У варианта %s указали название для декларации category_id = %s', $this->id, $this->product->categories[0]->id);
                                TelegramQueueJob::push($message, -509092185);
                            }
                        } else {
                            $nameForDeclaration = $this->product->categories[0]->name_boy_ru;
                        }
                    } else {
                        $nameForDeclaration = $this->product->categories[0]->name_girl_ru;
                    }
                } else {
                    $nameForDeclaration = $this->product->categories[0]->name_male_ru;
                }
            } else {
                $nameForDeclaration = $this->product->categories[0]->name_female_ru;
            }
        } catch (\Exception $exception) {
            $nameForDeclaration = '';
            $message = sprintf('!!!ALARM!!! У варианта %s ошибка в получении названия категории для декларации', $this->id);
            TelegramQueueJob::push($message, '',-509092185);

        }
        $nameForDeclaration .= ' ' . $this->product->marketBrand->name;

        $nameForDeclaration .= ' ' . $this->product->origin_name;

        $nameForDeclaration .= ', ' . $this->description;

        return $nameForDeclaration;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = [
            'id',
            'product_id',
            'market_id' => function() {
                return $this->product->market_id;
            },
            'image_has_bg' => function() {
                $market = \Yii::$app->markets->one($this->product->market_id);
                return $market->getImageHasBg();
            },
            'name' => function () {
                return $this->product->customTranslate->name ?? $this->product->name;
            },
            'brand' => function () {
                return $this->product->brand;
            },
            'sizeChart' => function () {
                return $this->sizeChart;
            },
            'size_plus' => function () {
                return @$this->marketSize->sizePlus ? 1 : null;
            },
            'size_kids' => function () {
                $sizeKids = @$this->marketSize->sizeKids;
                if (!empty($sizeKids)) {
                    return $sizeKids->type_id;
                }
                return null;
            },
            'sizeBottomLength' => function () {
                return $this->marketSize->sizeBottomLength ?? null;
            },
            'sizeKidsAge' => function () {
                return $this->marketSize->sizeKidsAge ?? null;
            },
            'usmall_url' => function() {
                try {
                    return $this->product->getUsmallUrl();
                } catch (\Exception $e) {
                    return '';
                }
            },
            'image',
            'images' => function () {
                return $this->images;
            },
            'description' => function () {
                return $this->description;
            },
            'price' => 'sellingPrice',
            'price_full' => 'sellingPriceFull',
            'price_detail' => function () {
                return $this->getPriceDetailSP();
            },
            'price_crypto' => function () {
                if (PercentCategoryBrandHelper::isEnabledByMarket($this->product->market_id)) {
                    PercentCategoryBrandHelper::setEnabled();
                    $sellingPriceCrypto = $this->getSellingPrice();
                    $priceCostCripto = $this->getPriceCost();
                    PercentCategoryBrandHelper::setDisabled();
                    $usdt = CryptoHelper::getUsdtRoundValue($sellingPriceCrypto);
                    $rateRealWithoutCustom = Yii::$app->currencyRate->getRateRealWithoutCustom();
                    return [
                        'rub' => $sellingPriceCrypto,
                        'usdt' => $usdt,
                        'rate' => $rateRealWithoutCustom,
                    ];
                }
            },
            'discount' => function () {
                if (Setting::enabled(Setting::PRODUCT_VARIANT_CUSTOM_PRICE)) {
                    //Если установлена кастомная цена, то null
                    if ($customPrice = $this->customPrice) {
                        if ($this->price_full) {
                            $fullPrice = $this->getSellingPriceFull();
                            return round((100 - (($customPrice->price * 100) / $fullPrice)));
                        }
                    }
                }

                return $this->discount;
            },
            'product_is_lost' => function () {
                if (!$this->product->enabledCategories || $this->product->market->isMarketDisabled()) {
                    return true;
                }
                return $this->product->is_lost;
            },
            'stock_count' => function () {
                if (!$this->product->enabledCategories || $this->product->market->isMarketDisabled()) {
                    return 0;
                }

                return $this->stock_count;
            },
            'origin_color',
            'origin_name' => function () {
                return $this->product->origin_name;
            },
            'is_custom_translated_name' => function () {
                return isset($this->product->customTranslate->name) ? 1 : 0;
            },
            'color_name' => function () {
                $marketColors = array_values($this->marketColors);
                if ($marketColors && $marketColors[0]->color) {
                    return $marketColors[0]->color->name;
                }
                return $this->origin_color;
            },
            'color_name_ru' => function () {
                $marketColors = array_values($this->marketColors);
                if (!empty($marketColors)) {
                    if ($marketColors[0]->color) {
                        return $marketColors[0]->color->name_ru;
                    }
                }
                return null;
            },
            'origin_size' => function () {
                return $this->marketSize->name ?? null;
            },
            'size' => function () {
                return $this->marketSize->size->name ?? null;
            },
            'russianSize' => function () {
                return $this->russianSizeName;
            },
            'dutyFee' => function () {
                if ($this->price > 220) {
                    return DutyFeeHelper::getDutyFee($this->id);
                }

                return null;
            },
            'expiration' => function () {
                $productVariantExpirationDate = $this->productVariantExpirationDate;
                if ($productVariantExpirationDate) {
                    return $productVariantExpirationDate->date_at;
                }

                return null;
            },
            'recommendName' => function () {
                return Yii::$app->params['recommendName'] ?? null;
            },
            'item_weight_real_time' => function () {
                return isset(Yii::$app->params['personalizeRealTime']) ? 1 : null;
            },
            'average_rating' => function () {
                return $this->product->productAverageRating->rating ?? null;
            },
        ];

        if (isset(Yii::$app->params['cartProductVariant']) && $this->product->market_id !== 29) {
            $functionNull = static function () { return null; };
            $fields['name'] = $functionNull;
            $fields['brand'] = $functionNull;
            $fields['origin_name'] = $functionNull;
            $fields['description'] = $functionNull;
        }

        //Для уменьшения размера ответа при разных запросах, например, рекомендации.
        if (isset(Yii::$app->params['catalogProductVariant'])) {
            unset(
                $fields['sizeChart'],
                $fields['sizeBottomLength'],
                $fields['sizeKidsAge'],
                $fields['usmall_url'],
                $fields['description'],
                $fields['price_detail'],
                $fields['price_crypto'],
                $fields['is_custom_translated_name'],
                $fields['size'],
                $fields['russianSize'],
                $fields['dutyFee'],
                $fields['expiration'],
                $fields['recommendName']
            );

            $fields['color_id'] = function () {
                $marketColors = $this->marketColors;
                if (!empty($marketColors) && $marketColors[0]->color) {
                    return $marketColors[0]->color->id;
                }
                return null;
            };
        }

        return $fields;
    }

    /**
     * Find size chart by unique composite key
     * @return SizeChartQuery
     */
    public function getSizeChart(): SizeChartQuery
    {
        $brandId = $this->product->brand_id;
        $genderCode = $this->product->gender_code;
        $sizeId = $this->marketSize->size_id ?? null;
        $subSizeId = $this->marketSize->sub_size_id ?? null;
        $sizeChartTypeId = $this->product->defaultCategory->size_chart_type_id ?? null;
        $sizeKidsAgeId = $this->marketSize->size_kids_age_id ?? null;
        $sizeBottomLengthId = $this->marketSize->size_bottom_length_id ?? null;

        $sizeChartQuery = SizeChart::find()
            ->forVariant($brandId, $genderCode, $sizeId, $subSizeId, $sizeChartTypeId, $sizeKidsAgeId, $sizeBottomLengthId);
        $sizeChartDependencyQuery = clone $sizeChartQuery;

        $dependency =  new DbDependency([
            'reusable' => true,
            'sql' => $sizeChartDependencyQuery->select(new Expression("MAX(updated_at)"))->createCommand()->getRawSql(),
        ]);

        return $sizeChartQuery->cache(1800, $dependency);
    }

    public function getColorNameEnRu()
    {
        $returnColor = null;
        $marketColors = $this->marketColors;
        if (!empty($marketColors)) {
            if ($marketColors[0]->color) {
                if (!$marketColors[0]->color->name_ru) {
                    $returnColor = $marketColors[0]->color->name;
                } else {
                    $returnColor = $marketColors[0]->color->name_ru;
                }
            }
        }

        return $returnColor;
    }

    public function getColorIdRu()
    {
        $returnColorId = 0;
        $marketColors = $this->marketColors;
        if (!empty($marketColors)) {
            if ($marketColors[0]->color) {
                $returnColorId = $marketColors[0]->color->id;
            }
        }

        return $returnColorId;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRmsskus()
    {
        return $this->hasOne(ProductVariantRmssku::class, ['product_variant_id' => 'id']);
    }

    public function getPriceWithPromocodes(): array
    {
        $return = [];
        $promocodes = Promocode::find()->active()->notPersonal()->andWhere(['>', 'ended_at', time()])->all();
        foreach ($promocodes as $promocode) {
            if ($this->isProductExistsInSphinxByPromocode($promocode)) {
                PromocodeHelper::$activePromocode = $promocode;
                $price = $this->getSellingPrice(true);
                $return[$promocode->id] = $promocode->name_code . ': ' . $price;
            }
        }
        return $return;
    }

    public function getPromocodes(): array
    {
        $promocodes = Promocode::find()->active()->notPersonal()->andWhere(['>', 'ended_at', time()])->all();

        $return = [];

        foreach ($promocodes as $promocode) {
            if ($this->isProductExistsInSphinxByPromocode($promocode)) {
                $return[$promocode->id] = $promocode->name_code;
            } else {
                if ($this->product->is_lost == Product::PRODUCT_IS_NOT_LOST && $this->stock_count > 0) {
                    if ($this->product->isDisabledMarket($this->product_id)) {
                        $return[$promocode->id] = "Продукт принадлежит выключенному магазину!";
                    } elseif ($promocodeValue = $this->checkProductAllowPromocode($promocode)) {
                        if ($this->isProductLostInSphinx()) {
                            $this->setProductVariantActiveInIndex();
                            $return[$promocode->id] = $promocodeValue . ": продукт был потерян на витрине. Проблема исправлена.";
                        } else {
                            $return[$promocode->id] = $promocodeValue . ": продукт отсутствует на витрине, отправлено уведомление о необходимости прокрутки индекса!";
                            $message = "При проверке промокода $promocode->name_code найден вариант, отсутствующий на витрине: $this->id. Необходимо прокрутить индекс!";
                            TelegramSender::send($message, TelegramChatIdSetting::getChatId(TelegramChatIdSetting::EVENTS_RED_LOGS));
                        }
                    }
                }
            }
        }
        return $return;
    }

    public function setProductVariantActiveInIndex()
    {
        Yii::$app->sphinx->createCommand()->update('product_variant', ['is_lost' => 0], ['product_id' => $this->product_id])->execute();
    }

    private function isProductExistsInSphinxByPromocode(Promocode $promocode): bool
    {
        $filterData = json_decode($promocode->filter_data, true);
        if (isset($filterData['product']) && $filterData['product'] == 'all') {
            $filterData = [];
        }

        $filter = new ProductVariantSphinxFilter($filterData);
        $query = $filter->searchQuery(true);
        $query->orderBy = null;
        $query->options = null;
        $query->andWhere(['is_lost' => 0]);
        $query->andWhere(['product_id' => $this->product_id]);
        $query->showMeta(true);
        $query->groupBy('product_id');
        $activeDataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        if ($activeDataProvider->totalCount > 0) {
            return true;
        }
        return false;
    }

    private function isProductLostInSphinx(): bool
    {
        $sphinxSearch = ProductVariantSphinx::find()
            ->where(['product_id' => $this->product_id, 'is_lost' => 1])
            ->count();
        if ($sphinxSearch > 0) {
            return true;
        }

        return false;
    }

    private function checkProductAllowPromocode(Promocode $promocode)
    {
        $filterData = json_decode($promocode->filter_data, true);
        $isAllowPromocode = false;
        if (isset($filterData['product']) && $filterData['product'] == 'all') {
            $isAllowPromocode = true;
        } else {
            foreach ($filterData as $key => $value) {
                if ($key === 'categories') {
                    $categoryIds = [];
                    foreach ($this->product->categories as $category) {
                        $categoryIds[] = $category->id;
                    }
                    if (array_intersect($categoryIds, $value)) {
                        $isAllowPromocode = true;
                    } else {
                        $isAllowPromocode = false;
                        break;
                    }
                } elseif ($key === 'brand_ids') {
                    if (in_array($this->product->brand_id, $value)) {
                        $isAllowPromocode = true;
                    } else {
                        $isAllowPromocode = false;
                        break;
                    }
                } elseif ($key === 'market_id') {
                    if (in_array($this->product->market_id, $value)) {
                        $isAllowPromocode = true;
                    } else {
                        $isAllowPromocode = false;
                        break;
                    }
                } else {
                    $message = "В промокоде $promocode->name_code есть неучтённые фильтры для проверки: $key";
                    TelegramSender::send($message, TelegramChatIdSetting::getChatId(TelegramChatIdSetting::EVENTS_YELLOW_LOGS));

                    return $promocode->name_code . ": Есть неучтённые фильтры в промокоде!";
                }
            }
        }

        if ($isAllowPromocode) {
            return $promocode->name_code;
        }
        return false;
    }

    /**
     * Checks if variant in google
     * @return bool
     */
    public function isInGoogle(): bool
    {
        $googleRecommendHelper = new GoogleRecommendHelper();
        try {
            $product = $googleRecommendHelper->getProductGoogle($this->id);
        } catch (\Exception $e) {
            $product = json_decode($e->getMessage(), true);
        }

        if (is_object($product)) {
            $productArray = json_decode($product->serializeToJsonString(), true);
            if(isset($productArray['id']) && $productArray['id'] == $this->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDisabledProductVariant()
    {
        return $this->hasOne(DisabledProductVariant::class, ['product_variant_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProductVariantExpirationDate()
    {
        return $this->hasOne(ProductVariantExpirationDate::class, ['product_variant_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProductVariantPrice()
    {
        return $this->hasOne(ProductVariantPrice::class, ['product_variant_id' => 'id']);
    }

    /**
     * Проверяем, что вариант должен быть в индексе, требуется для применения промокода,
     * т.к. часто из-за отсутствия в индексе, к части товаров промокод не применяется
     * ВАЖНО поддерживать в актуальном состоянии условие where
     * @param $productId
     * @param $filterData
     * @return bool
     */
    public static function checkIfVariantShouldBeInIndex($productId, $filterData): bool
    {
        $check = false;

        if (isset($filterData['categories']) || isset($filterData['brand_ids'])) {
            $query = self::find()
                ->select('pv.id')
                ->from(['pv' => self::tableName()])
                ->joinWith([
                    'product AS p',
                    'product.brand AS b',
                    'product.categories AS c',
                    'marketColors AS mc',
                    'marketSize AS ms',
                    'product.categories.disabledCategory AS dc',
                ])
                ->leftJoin(['dcg' => 'disabled_category_gender'], 'c.id = dcg.category_id AND p.gender_code = dcg.gender_code')
                ->leftJoin(['dcb' => 'disabled_category_brand'], 'dcb.brand_id = p.brand_id AND dcb.category_id = c.id')
                ->leftJoin(['pbByProductId' => 'product_block'], 'p.id = pbByProductId.entity_id and pbByProductId.type = 1')
                ->leftJoin(['pbByMarketId' => 'product_block'], 'p.market_brand_id = pbByMarketId.entity_id and pbByMarketId.type = 2')
                ->innerJoin(['c2' => 'category'], 'c2.ns_lkey <= c.ns_lkey AND c2.ns_rkey >= c.ns_rkey AND c2.ns_depth > 0')
                ->where([
                    'and',
                    ['is_lost' => 0],
                    ['disabled' => 0],
                    ['not', ['p.brand_id' => null]],
                    ['not', ['p.default_category_id' => null]],
                    ['not', ['product_variant_market_color.market_color_id' => null]],
                    ['not', ['ms.size_id' => null]],
                    ['not', ['mc.color_id' => null]],
                    ['not', ['c.id' => null]],
                    ['dc.category_id' => null],
                    ['dcg.gender_code' => null],
                    ['dcb.category_id' => null],
                    ['not', ['p.gender_code' => null]],
                    ['>', 'pv.stock_count', 0],
                    ['or', ['<', 'pv.price', 500], ['p.market_id' => [17, 28, 35]]],
                    ['pbByProductId.entity_id' => null],
                    ['pbByMarketId.entity_id' => null],
                    ['p.id' => $productId]
                ])
                ->groupBy(['pv.id', 'product_variant_market_color.market_color_id']);

            $query->andFilterWhere(['c2.id' => $filterData['categories'] ?? []]);
            $query->andFilterWhere(['p.brand_id' => $filterData['brand_ids'] ?? []]);

            $check = (bool)$query->count();
        }

        return  $check;
    }

    /**
     * Trying to find why sizes changing between order and redeem
     * @return void
     */
    private function checkTracker()
    {
        $conditions = [
            'market_id' => $this->product->market_id ?? 0,
            'product_variant_id' => $this->id,
            'remote_code' => $this->remote_code,
            'market_size_id' => $this->market_size_id,
            'origin_color' => $this->origin_color,
            'origin_size' => $this->origin_size,
        ];

        if (!TrackerKohlsSize::find()->where($conditions)->exists()) {
            (new TrackerKohlsSize($conditions))->save(false);
        }
    }
}
