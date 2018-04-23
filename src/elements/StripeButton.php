<?php
/**
 * EnupalStripe plugin for Craft CMS 3.x
 *
 * @link      https://enupal.com/
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\stripe\elements;

use Craft;
use craft\base\Element;
use craft\behaviors\FieldLayoutBehavior;
use craft\elements\db\ElementQueryInterface;
use enupal\backup\models\Settings;
use enupal\stripe\enums\DiscountType;
use enupal\stripe\validators\DiscountValidator;
use yii\base\ErrorHandler;
use craft\helpers\UrlHelper;
use craft\elements\actions\Delete;
use Yii;

use enupal\stripe\elements\db\StripeButtonsQuery;
use enupal\stripe\records\StripeButton as StripeButtonRecord;
use enupal\stripe\Stripe as StripePlugin;
use craft\validators\UniqueValidator;

/**
 * StripeButton represents a entry element.
 */
class StripeButton extends Element
{
    /**
     * @inheritdoc
     */
    public $id;

    /**
     * @var string Name.
     */
    public $name;

    public $companyName;

    /**
     * @var string Sku
     */
    public $handle;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var string Language
     */
    public $language;

    /**
     * @var int Amount
     */
    public $amount;

    /**
     * @inheritdoc
     */
    public $enabled;

    public $quantity;
    public $hasUnlimitedStock;
    public $customerQuantity;
    public $soldOutMessage;
    public $discountType;
    public $discount;
    public $shippingAmount;
    public $itemWeight;
    public $itemWeightUnit;
    public $priceMenuName;
    public $priceMenuOptions;

    public $showItemName;
    public $showItemPrice;
    public $showItemCurrency;
    // Button
    public $buttonText;
    public $paymentButtonProcessingText;
    public $returnUrl;

    public $amountType;
    public $minimumAmount;
    public $customAmountLabel;
    public $verifyZip;
    public $enableBillingAddress;
    public $enableShippingAddress;
    public $logoImage;
    public $enableRememberMe;

    public $enableRecurringPayment;
    public $recurringPaymentType;

    protected $env;
    protected $paypalUrl;
    protected $ipnUrl;
    protected $publishableKey;

    /**
     * @var Settings
    */
    protected $settings;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => self::class
            ],
        ]);
    }

    public function init()
    {
        parent::init();

        if (!$this->settings){
            $this->settings = StripePlugin::$app->settings->getSettings();
        }

        $this->env =  $this->settings->testMode ? 'www.sandbox' : 'www';

        $this->returnUrl = $this->returnUrl ? $this->returnUrl : $this->settings->returnUrl;
        $this->currency = $this->currency ? $this->currency : $this->settings->defaultCurrency;
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function getReturnUrl()
    {
        // by default return to the same page
        $returnUrl = '';

        if ($this->returnUrl){
            $returnUrl = $this->getSiteUrl($this->returnUrl);
        }

        return $returnUrl;
    }

    /**
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getIpnUrl()
    {
        $this->ipnUrl = Craft::$app->getSites()->getPrimarySite()->baseUrl.'enupal-stripe/ipn';

        return $this->ipnUrl;
    }

    /**
     * @return string
     */
    public function getPublishableKey()
    {
        $this->publishableKey = StripePlugin::$app->settings->getPublishableKey();

        return $this->publishableKey;
    }

    /**
     * @return string
     */
    public function getTax()
    {
        $tax = $this->settings->tax ?? null;

        return $tax;
    }

    /**
     * @return string
     */
    public function getTaxType()
    {
        $taxType = null;

        switch ($this->settings->taxType) {
            case DiscountType::RATE:
                {
                    $taxType = 'tax_rate';
                    break;
                }
            case DiscountType::AMOUNT:
                {
                    $taxType = 'rate';
                    break;
                }
        }

        return $taxType;
    }

    /**
     * @return string
     */
    public function getDiscount()
    {
        $discount = $this->discount ?? null;

        return $discount;
    }

    /**
     * @return string
     */
    public function getDiscountType()
    {
        $discountType = null;

        switch ($this->discountType) {
            case DiscountType::RATE:
                {
                    $discountType = 'discount_rate';
                    break;
                }
            case DiscountType::AMOUNT:
                {
                    $discountType = 'discount_amount';
                    break;
                }
        }

        return $discountType;
    }

    /**
     * Returns the field context this element's content uses.
     *
     * @access protected
     * @return string
     */
    public function getFieldContext(): string
    {
        return 'enupalPaypal:'.$this->id;
    }

    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return StripePlugin::t('Stripe Buttons');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'paypal-buttons';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        $behaviors = $this->getBehaviors();
        $fieldLayout = $behaviors['fieldLayout'];

        return $fieldLayout->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'enupal-stripe/buttons/edit/'.$this->id
        );
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function __toString()
    {
        try {
            // @todo - For some reason the Title returns null possible Craft3 bug
            return $this->name;
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     *
     * @return StripeButtonsQuery The newly created [[StripeButtonsQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new StripeButtonsQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => StripePlugin::t('All Buttons'),
            ]
        ];

        // @todo add groups

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Delete
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => StripePlugin::t("Are you sure you want to delete this Stripe Button, and all of it's orders?"),
            'successMessage' => StripePlugin::t('Payapal Buttons deleted.'),
        ]);

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['name', 'handle'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        $attributes = [
            'elements.dateCreated' => StripePlugin::t('Date Created'),
            'name' => StripePlugin::t('Name'),
            'handle' => StripePlugin::t('Handle')
        ];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes['name'] = ['label' => StripePlugin::t('Name')];
        $attributes['handle'] = ['label' => StripePlugin::t('Handle')];
        $attributes['amount'] = ['label' => StripePlugin::t('Amount')];
        $attributes['dateCreated'] = ['label' => StripePlugin::t('Date Created')];

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = ['name', 'amount', 'handle', 'dateCreated'];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'amount':
                {
                    if ($this->amount) {
                        return $this->getAmountAsCurrency();
                    }

                    return Craft::$app->getFormatter()->asCurrency($this->$attribute * -1, $this->currency);
                }
            case 'dateCreated':
                {
                    return $this->dateCreated->format("Y-m-d H:i");
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateCreated';
        return $attributes;
    }

    /**
     * @inheritdoc
     * @throws Exception if reasons
     */
    public function afterSave(bool $isNew)
    {
        $record = new StripeButtonRecord();
        // Get the StripeButton record
        if (!$isNew) {
            $record = StripeButtonRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid StripeButton ID: '.$this->id);
            }
        } else {
            $record->id = $this->id;
        }

        $record->name = $this->name;
        $record->companyName = $this->companyName;

        $record->handle = $this->handle;
        $record->currency = $this->currency;
        $record->language = $this->language;
        $record->amountType = $this->amountType;
        $record->amount = $this->amount;
        $record->minimumAmount = $this->minimumAmount;
        $record->customAmountLabel = $this->customAmountLabel;
        $record->logoImage = $this->logoImage;
        $record->enableRememberMe = $this->enableRememberMe;
        $record->quantity = $this->quantity;
        $record->hasUnlimitedStock = $this->hasUnlimitedStock;
        $record->discountType = $this->discountType;
        $record->discount = $this->discount;

        $record->verifyZip = $this->verifyZip;
        $record->enableBillingAddress = $this->enableBillingAddress;
        $record->enableShippingAddress = $this->enableShippingAddress;
        $record->customerQuantity = $this->customerQuantity ? $this->customerQuantity : 0;

        $record->enableRecurringPayment = $this->enableRecurringPayment;
        $record->recurringPaymentType = $this->recurringPaymentType;

        $record->returnUrl = $this->returnUrl;
        $record->buttonText = $this->buttonText;
        $record->paymentButtonProcessingText = $this->paymentButtonProcessingText;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['name', 'handle'], UniqueValidator::class, 'targetClass' => StripeButtonRecord::class],
            [
                ['discount'],
                DiscountValidator::class
            ],
        ];
    }

    /**
     * @param $url
     *
     * @return string
     * @throws \yii\base\Exception
     */
    private function getSiteUrl($url)
    {
        if (UrlHelper::isAbsoluteUrl($url)){
            return $url;
        }

        return UrlHelper::siteUrl($url);
    }

    /**
     * Returns a complete PayPal Button for display in template
     *
     * @param array|null $options
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function displayButton(array $options = null)
    {
        return StripePlugin::$app->buttons->getButtonHtml($this->handle, $options);
    }


    /**
     * @return string
     * @throws \yii\web\ServerErrorHttpException
     */
    public function getPublicData()
    {
        $info = Craft::$app->getInfo();
        $logoUrl = null;
        $logoAsset = $this->getLogoAsset();

        if ($logoAsset){
            $logoUrl = $logoAsset->getUrl();
        }

        $publicData = [
            'handle' => $this->handle,
            'amountType' => $this->amountType,
            'customerQuantity' => $this->customerQuantity ? (boolean)$this->customerQuantity : false,
            'buttonText' => $this->buttonText,
            'paymentButtonProcessingText' => $this->paymentButtonProcessingText ? $this->getButtonText($this->paymentButtonProcessingText) : $this->getButtonText(),
            'pbk' => $this->getPublishableKey(),
            'testMode' => (boolean)$this->settings->testMode,
            'customAmountLabel' => Craft::$app->view->renderString($this->customAmountLabel ?? '' , ['button' => $this]),
            'stripe' => [
                'description' => $this->name,
                'name' => $this->companyName ?? $info->name,
                'currency' => $this->currency ?? 'USD',
                'locale' => $this->language,
                'amount' => $this->amount,
                'image' => $logoUrl,
                'allowRememberMe' => (boolean)$this->enableRememberMe,
                'zipCode' => (boolean)$this->verifyZip,
            ]
        ];

        // Booleans
        if ($this->enableShippingAddress){
            $publicData['stripe']['shippingAddress'] = true;
            $publicData['stripe']['billingAddress'] = true;
        }

        if ($this->enableBillingAddress){
            $publicData['stripe']['billingAddress'] = true;
        }

        return json_encode($publicData);
    }

    /**
     * @return \craft\base\ElementInterface|null
     */
    public function getLogoAsset()
    {
        $logoElement = null;

        if ($this->logoImage) {
            $logo = $this->logoImage;
            if (is_string($logo)) {
                $logo = json_decode($this->logoImage);
            }

            if (count($logo)) {
                $logoElement = Craft::$app->elements->getElementById($logo[0]);
            }
        }

        return $logoElement;
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getButtonText($default = "Pay with card")
    {
        $buttonText = Craft::t('site', $default);

        if ($this->buttonText){
            $buttonText =  Craft::$app->view->renderString($this->buttonText, ['button' => $this]);
        }

        return $buttonText;
    }

    /**
     * @param string $default
     *
     * @return string
     */
    public function getCustomLabel($default = "Pay what you want:")
    {
        $text = Craft::t('site', $default);

        if ($this->customAmountLabel){
            $text = $this->customAmountLabel;
        }

        return $text;
    }


    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getCurrencySymbol()
    {
        $pattern = Craft::$app->formatter->asCurrency(123, $this->currency);

        // the spacings between currency symbol and number are ignored, because
        // a single space leads to better readability in combination with input
        // fields
        // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)
        preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123([,.]0+)?[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/u', $pattern, $matches);

        $symbol = '';

        if (!empty($matches[1])) {
            $symbol = $matches[1];
        } elseif (!empty($matches[3])) {
            $symbol = $matches[3];
        }

        return $symbol;
    }



    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getAmountAsCurrency()
    {
        return Craft::$app->getFormatter()->asCurrency($this->amount, $this->currency);
    }
}