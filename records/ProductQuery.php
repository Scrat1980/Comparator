<?php

namespace app\records;

use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;

/**
 * Product Query
 */
class ProductQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->alias('p');
    }

    /**
     * @param int $id
     * @return $this
     */
    public function byId(int $id)
    {
        return $this->andWhere(['p.id' => $id]);
    }

    /**
     * @param $ids
     * @return ProductQuery
     */
    public function byIds($ids)
    {
        return $this->andWhere(['in', 'p.id', $ids]);
    }

    /**
     * @param int $categoryId
     * @return $this
     */
    public function byCategory(int $categoryId)
    {
        return $this
            ->innerJoin(['pc' => 'product_category'], '{{pc}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pc.category_id' => $categoryId]);
    }

    /**
     * @param int $brandId
     * @return $this
     */
    public function byBrand(int $brandId)
    {
        return $this->andWhere(['brand_id' => $brandId]);
    }

    /**
     * @param int|int[] $brandId
     * @return $this
     */
    public function byMarketBrands($brandId)
    {
        return $this->andWhere(['market_brand_id' => $brandId]);
    }

    /**
     * @param string $code
     * @return $this
     */
    public function byGender(string $code)
    {
        return $this->andWhere(['gender_code' => $code]);
    }

    /**
     * @param int $colorId
     * @return $this
     */
    public function byColor(int $colorId)
    {
        $query = (new Query())
            ->from(['mc' => 'market_color'])
            ->innerJoin(['pvmc' => 'product_variant_market_color'], '{{pvmc}}.[[market_color_id]] = {{mc}}.[[id]]')
            ->innerJoin(['pv' => 'product_variant'], '{{pv}}.[[id]] = {{pvmc}}.[[product_variant_id]]')
            ->andWhere('{{pv}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['mc.color_id' => $colorId]);
        return $this->andWhere(['exists', $query]);
    }

    /**
     * @param int $sizeId
     * @return $this
     */
    public function bySize(int $sizeId)
    {
        $query = (new Query())
            ->from(['ms' => 'market_size'])
            ->innerJoin(['pv' => 'product_variant'], '{{pv}}.[[market_size_id]] = {{ms}}.[[id]]')
            ->andWhere('{{pv}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['ms.size_id' => $sizeId]);
        return $this->andWhere(['exists', $query]);
    }

    /**
     * @param $remoteCode
     * @return ProductQuery
     */
    public function byRemoteCode($remoteCode)
    {
        return $this->andWhere(['p.remote_code' => $remoteCode]);
    }

    /**
     * @param int $marketCategoryId
     * @return $this
     */
    public function byMarketCategory(int $marketCategoryId)
    {
        return $this
            ->innerJoin(['pmc' => 'product_market_category'], '{{pmc}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pmc.market_category_id' => $marketCategoryId]);
    }

    /**
     * @param int $marketCategoryId
     * @return $this
     */
    public function byMarketCategoryThatUnlinked(int $marketCategoryId)
    {
        return $this
            ->innerJoin(['pmc' => 'product_market_category'], '{{pmc}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pmc.market_category_id' => $marketCategoryId])
            ->andWhere([
                'not exists',
                (new Query())
                    ->from(['pmc2' => 'product_market_category'])
                    ->innerJoin(['mc2' => 'market_category'], 'mc2.id = pmc2.market_category_id')
                    ->where('pmc2.product_id = p.id AND mc2.category_id IS NOT NULL')
            ]);
    }

    /**
     * @param int $marketBrandId
     * @return $this
     */
    public function byMarketBrand(int $marketBrandId)
    {
        return $this->andWhere(['p.market_brand_id' => $marketBrandId]);
    }

    /**
     * @param int $marketGenderId
     * @return $this
     */
    public function byMarketGender(int $marketGenderId)
    {
        return $this
            ->innerJoin(['pmg' => 'product_market_gender'], '{{pmg}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pmg.market_gender_id' => $marketGenderId]);
    }

    /**
     * @param int $marketColorId
     * @return $this
     */
    public function byMarketColor(int $marketColorId)
    {
        $query = (new Query())
            ->from(['pvmc' => 'product_variant_market_color'])
            ->innerJoin(['pv' => 'product_variant'], '{{pv}}.[[id]] = {{pvmc}}.[[product_variant_id]]')
            ->andWhere('{{pv}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pvmc.market_color_id' => $marketColorId]);
        return $this->andWhere(['exists', $query]);
    }

    /**
     * @param int $marketSizeId
     * @return $this
     */
    public function byMarketSize(int $marketSizeId)
    {
        $query = (new Query())
            ->from(['pv' => 'product_variant'])
            ->andWhere('{{pv}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pv.market_size_id' => $marketSizeId]);
        return $this->andWhere(['exists', $query]);
    }

    /**
     * @param int $marketId
     * @param string $remoteCode
     * @return $this
     */
    public function byCode(int $marketId, string $remoteCode)
    {
        return $this
            ->byMarket($marketId)
            ->andWhere(['p.remote_code' => $remoteCode]);
    }

    /**
     * @param int $marketId
     * @return $this
     */
    public function byMarket(int $marketId)
    {
        return $this->andWhere(['p.market_id' => $marketId]);
    }

    /**
     * @return $this
     */
    public function disabled()
    {
        return $this->andWhere(['p.disabled' => true]);
    }

    /**
     * @return $this
     */
    public function detected()
    {
        return $this->active()->andWhere(['p.translated_at' => null]);
    }

    /**
     * @return $this
     */
    public function active()
    {
        return $this->andWhere(['p.is_lost' => false]);
    }

    /**
     * @return $this
     */
    public function translated()
    {
        return $this
            ->andWhere('{{p}}.[[updated_at]] <= {{p}}.[[translated_at]]')
            ->active();
    }

    /**
     * @return $this
     */
    public function approved()
    {
        return $this
            //TODO потом включить Выключил чтобы Володя мог тестировать
//            ->andWhere(['is not', 'p.approved_at', null])
            ->active();
    }

    /**
     * @return $this
     * @deprecated
     * @see active()
     */
    public function notLost()
    {
        return $this->active();
    }

    /**
     * @param int $parsingId
     * @return $this
     */
    public function addedBy(int $parsingId)
    {
        return $this->andWhere(['p.first_parsing_id' => $parsingId]);
    }

    /**
     * @param int $parsingId
     * @return $this
     */
    public function lostBy(int $parsingId)
    {
        return $this
            ->andWhere(['p.last_parsing_id' => $parsingId])
            ->lost();
    }

    /**
     * @return $this
     */
    public function lost()
    {
        return $this->andWhere(['p.is_lost' => true]);
    }

    /**
     * @return $this
     */
    public function untranslated()
    {
        return $this->active()
            ->andWhere('{{p}}.[[updated_at]] > IFNULL({{p}}.[[translated_at]], 0)');
    }

    /**
     * @return $this
     */
    public function nextAfter(Product $product)
    {
        return $this
            ->andWhere(['>', 'id', $product->id])
            ->orderBy(['id' => SORT_ASC])
            ->limit(1);
    }

    /**
     * @param int $snippetId
     */
    public function byDescriptionSnippet($snippetId)
    {
        return $this
            ->innerJoin(['pds' => 'product_description_snippet'], '{{pds}}.[[product_id]] = {{p}}.[[id]]')
            ->andWhere(['pds.snippet_id' => $snippetId]);
    }

    /**
     * @param int[] $ids
     * @return $this
     */
    public function setProductIds($ids)
    {
        return $this
            ->andWhere(['id' => $ids])
            ->orderBy([new Expression('FIELD (id, ' . implode(',', $ids) . ')')]);
    }

    public function byDefaultCategoryId($defaultCategoryId)
    {
        return $this->andWhere(['default_category_id' => $defaultCategoryId]);
    }

    /**
     * @inheritdoc
     * @return Product[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Product|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
