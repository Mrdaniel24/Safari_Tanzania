<?php
declare(strict_types=1);

/**
 * Pricing helper: calculates subtotal, VAT and total.
 * $lines = [ ['price'=>float, 'quantity'=>int, 'nights'=>int], ... ]
 */
function calculatePricing(array $lines, float $vatRate = 0.18): array {
    $subtotal = 0.0;
    foreach ($lines as $ln) {
        $p = (float)($ln['price'] ?? 0.0);
        $q = (int)($ln['quantity'] ?? 1);
        $n = (int)($ln['nights'] ?? 1);
        $subtotal += $p * $q * $n;
    }
    $vat = round($subtotal * $vatRate, 2);
    $total = round($subtotal + $vat, 2);
    return ['subtotal' => round($subtotal,2), 'vat' => $vat, 'total' => $total];
}
