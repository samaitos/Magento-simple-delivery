<?php
declare(strict_types=1);

namespace Walletking\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

/**
 * One delivery method with a fixed price and an optional free-shipping threshold.
 * All settings are read for the shopper's store view.
 */
class Simple extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'wkshipping';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    private ResultFactory $rateResultFactory;
    private MethodFactory $rateMethodFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
    }

    /**
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $price = self::parseAmount($this->getConfigData('price'));
        if ($price === null) {
            return false;
        }

        if ($request->getFreeShipping()
            || $this->reachesFreeShipping($request, self::parseAmount($this->getConfigData('free_shipping_from')))
        ) {
            $price = 0.0;
        }

        $method = $this->rateMethodFactory->create();
        $method->setCarrier(self::CODE);
        $method->setCarrierTitle(__((string)$this->getConfigData('title')));
        $method->setMethod(self::CODE);
        $method->setMethodTitle(__((string)$this->getConfigData('name')));
        $method->setPrice($price);
        $method->setCost($price);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        return [self::CODE => (string)__((string)$this->getConfigData('name'))];
    }

    private function reachesFreeShipping(RateRequest $request, ?float $freeFrom): bool
    {
        if ($freeFrom === null) {
            return false;
        }

        $total = (float)$request->getPackageValueWithDiscount();
        if ($this->getConfigFlag('free_shipping_including_tax')) {
            if ($request->getBaseSubtotalWithDiscountInclTax()) {
                $total = (float)$request->getBaseSubtotalWithDiscountInclTax();
            } elseif ($request->getBaseSubtotalInclTax()) {
                $discount = (float)$request->getPackageValue() - (float)$request->getPackageValueWithDiscount();
                $total = (float)$request->getBaseSubtotalInclTax() - $discount;
            }
        }

        return $total >= $freeFrom;
    }

    /**
     * Accepts "49", "49.50", "49,50" and "1 000"; returns null for empty or anything else.
     *
     * @param mixed $value
     */
    private static function parseAmount($value): ?float
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim((string)$value));
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }
        return (float)$value;
    }
}
