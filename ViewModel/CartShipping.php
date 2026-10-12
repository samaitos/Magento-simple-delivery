<?php
declare(strict_types=1);

namespace Walletking\Shipping\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Walletking\Shipping\Model\Carrier\Simple;

/**
 * Delivery price for the cart summary, worked out on the server from the quote that the cart page
 * has already loaded, so no extra request or JavaScript is needed.
 */
class CartShipping implements ArgumentInterface
{
    private ScopeConfigInterface $scopeConfig;
    private CheckoutSession $checkoutSession;
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $checkoutSession,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @return array{free: bool, price: string, remaining: string|null}|null null when nothing should be shown
     */
    public function getSummary(): ?array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getItemsCount() || $quote->isVirtual()) {
            return null;
        }

        $storeId = (int)$quote->getStoreId();
        if (!$this->scopeConfig->isSetFlag('carriers/wkshipping/active', ScopeInterface::SCOPE_STORE, $storeId)) {
            return null;
        }
        $price = Simple::parseAmount($this->config('price', $storeId));
        if ($price === null) {
            return null;
        }
        $freeFrom = Simple::parseAmount($this->config('free_shipping_from', $storeId));

        $address = $quote->getShippingAddress();
        if ($this->scopeConfig->isSetFlag(
            'carriers/wkshipping/free_shipping_including_tax',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            // Discount amounts are negative.
            $total = (float)$address->getBaseSubtotalTotalInclTax() + (float)$address->getBaseDiscountAmount();
        } else {
            $total = (float)$quote->getBaseSubtotalWithDiscount();
        }

        $free = $price <= 0.0
            || (bool)(int)$address->getFreeShipping()
            || ($freeFrom !== null && $total >= $freeFrom);

        return [
            'free' => $free,
            'price' => $this->format($price),
            'remaining' => !$free && $freeFrom !== null ? $this->format($freeFrom - $total) : null,
        ];
    }

    private function config(string $field, int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue('carriers/wkshipping/' . $field, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === null ? null : (string)$value;
    }

    private function format(float $baseAmount): string
    {
        return (string)$this->priceCurrency->convertAndFormat($baseAmount, false);
    }
}
