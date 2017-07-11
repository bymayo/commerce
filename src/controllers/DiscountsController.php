<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\Discount;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Discounts Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class DiscountsController extends BaseCpController
{

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();
        return $this->renderTemplate('commerce/promotions/discounts/index', compact('discounts'));
    }


    public function actionEdit(int $id = null, Discount $discount = null): Response
    {
        $variables = [
            'id' => $id,
            'discount' => $discount,
        ];

        $variables['productElementType'] = Product::class;

        if (!$variables['discount']) {
            if ($variables['id']) {

                $variables['discount'] = Plugin::getInstance()->getDiscounts()->getDiscountById($variables['id']);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = new Discount();
            }
        }

        if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Discount');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        //getting product types maps
        $types = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $variables['types'] = ArrayHelper::map($types, 'id', 'name');

        $variables['products'] = null;
        $products = $productIds = [];
        if (!$variables['id']) {
            $productIds = explode('|', Craft::$app->getRequest()->getParam('productIds'));
        } else {
            $productIds = $variables['discount']->getProductIds();
        }
        foreach ($productIds as $productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById((int) $productId);
            if ($product) {
                $products[] = $product;
            }
        }
        $variables['products'] = $products;

        return $this->renderTemplate('commerce/promotions/discounts/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $discount = new Discount();

        // Shared attributes
        $fields = [
            'id',
            'name',
            'description',
            'enabled',
            'stopProcessing',
            'sortOrder',
            'purchaseTotal',
            'purchaseQty',
            'maxPurchaseQty',
            'freeShipping',
            'excludeOnSale',
            'code',
            'perUserLimit',
            'perEmailLimit',
            'totalUseLimit'
        ];
        $request = Craft::$app->getRequest();
        foreach ($fields as $field) {
            $discount->$field = $request->getParam($field);
        }

        $discountAmountsFields = [
            'baseDiscount',
            'perItemDiscount'
        ];
        foreach ($discountAmountsFields as $field) {
            $discount->$field = $request->getParam($field) * -1;
        }

        $dateFields = [
            'dateFrom',
            'dateTo'
        ];
        foreach ($dateFields as $field) {
            $discount->$field = (($date = $request->getParam($field)) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $discount->$date);
        }

        // Format into a %
        $percentDiscountAmount = $request->getParam('percentDiscount');
        $localeData = Craft::$app->getLocale();
        $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);
        if (strpos($percentDiscountAmount, $percentSign) or (float) $percentDiscountAmount >= 1) {
            $discount->percentDiscount = (float) $percentDiscountAmount / -100;
        } else {
            $discount->percentDiscount = (float) $percentDiscountAmount * -1;
        }

        $products = $request->getParam('products', []);
        if (!$products) {
            $products = [];
        }

        $productTypes = $request->getParam('productTypes', []);
        if (!$productTypes) {
            $productTypes = [];
        }

        $groups = $request->getParam('groups', []);
        if (!$groups) {
            $groups = [];
        }

        // Save it
        if (Plugin::getInstance()->getDiscounts()->saveDiscount($discount, $groups, $productTypes,
            $products)
        ) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save discount.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['discount' => $discount]);
    }

    /**
     *
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredParam('ids'));
        if ($success = Plugin::getInstance()->getDiscounts()->reorderDiscounts($ids)) {
            return $this->asJson(['success' => $success]);
        };

        return $this->asJson(['error' => Craft::t("commerce", "Couldn’t reorder discounts.")]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getDiscounts()->deleteDiscountById($id);
        $this->asJson(['success' => true]);
    }

    /**
     * @throws HttpException
     */
    public function actionClearCouponUsageHistory()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getDiscounts()->clearCouponUsageHistoryById($id);

        $this->asJson(['success' => true]);
    }

}
