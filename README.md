# Walletking Delivery

A simple delivery method for Magento 2.4.5 (module `Walletking_Shipping`, carrier code
`wkshipping`): a fixed price, with an optional free-shipping threshold, both set per store view.
It is a separate module from `Walletking_Checkout`, so it can be installed, enabled and changed on
its own. Because it is an ordinary Magento carrier it shows up in every checkout: the new
Walletking checkout, Firecheckout and Magento's own.

## Install

Copy this repository to `app/code/Walletking/Shipping` (or require `walletking/module-shipping` with
Composer), then:

```
bin/magento module:enable Walletking_Shipping
bin/magento setup:upgrade
bin/magento cache:flush
```

## Settings

**Stores > Configuration > Sales > Delivery Methods > Walletking Delivery**. Every setting can be
set per store view, so the method can be turned on for one store view at a time.

| Setting | What it does |
| --- | --- |
| Enabled | Shows the method in the selected store view. Off by default. |
| Title | Carrier name shown to the shopper (default "Delivery"). |
| Method name | Shown under the title, for example "1-3 business days" (default "Standard delivery"). |
| Price | The delivery price in the base currency. `49.50` and `49,50` both work. Required. |
| Free shipping from | Cart total from which delivery is free. Empty = never free. |
| Free shipping threshold includes tax | Yes (default): compare the cart total including tax, after discounts. No: excluding tax. |
| Show delivery price in cart | Yes (default): the cart summary shows the price, or "Free delivery", and how much is left to free delivery. |
| Ship to Applicable / Specific Countries | Magento's standard country limit. Default: all allowed countries. |
| Sort order | Position among the other delivery methods. |

How the price is chosen:

- The price is 0 when the cart reaches "Free shipping from", or when a cart price rule gives free
  shipping. Otherwise it is the configured price.
- Prices and thresholds are in the store's base currency; Magento converts them to the currency
  the shopper sees.
- The default title and method name are translated with the module's `i18n/*.csv` files (11
  languages). A title you type in the admin is shown as typed, so set it per store view for each
  language.

## Speed

The module is built to add as little as possible to the cart and checkout:

- No database tables, observers, plugins, cron jobs or queues. The carrier reads a few settings
  from Magento's config cache and does one comparison.
- No JavaScript or CSS. The cart line is plain HTML rendered on the server from the cart that the
  page has already loaded, so it adds no requests. It updates when the cart page reloads (after
  changing quantities or applying a discount code).
