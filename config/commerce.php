<?php
return [
    'base_currency' => env('BASE_CURRENCY', 'EGP'),
    // Existing non-EGP wallets remain in their historical currency until an explicit FX conversion is performed.
    'allow_cross_currency_wallet_transfer' => false,
];
