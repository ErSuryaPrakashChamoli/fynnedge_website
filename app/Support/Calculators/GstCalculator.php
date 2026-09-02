<?php

namespace App\Support\Calculators;

class GstCalculator
{
    /**
     * Adds GST on top of a base (exclusive) amount.
     *
     * @return array{base_amount: float, cgst: float, sgst: float, gst_amount: float, total_amount: float}
     */
    public static function addGst(float $baseAmount, float $ratePercent): array
    {
        if ($baseAmount <= 0) {
            return ['base_amount' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'gst_amount' => 0.0, 'total_amount' => 0.0];
        }

        $gstAmount = $baseAmount * $ratePercent / 100;

        return [
            'base_amount' => round($baseAmount, 2),
            'cgst' => round($gstAmount / 2, 2),
            'sgst' => round($gstAmount / 2, 2),
            'gst_amount' => round($gstAmount, 2),
            'total_amount' => round($baseAmount + $gstAmount, 2),
        ];
    }

    /**
     * Backs out GST already included in a total (inclusive) amount.
     *
     * @return array{base_amount: float, cgst: float, sgst: float, gst_amount: float, total_amount: float}
     */
    public static function removeGst(float $totalAmount, float $ratePercent): array
    {
        if ($totalAmount <= 0) {
            return ['base_amount' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'gst_amount' => 0.0, 'total_amount' => 0.0];
        }

        $baseAmount = $totalAmount / (1 + $ratePercent / 100);
        $gstAmount = $totalAmount - $baseAmount;

        return [
            'base_amount' => round($baseAmount, 2),
            'cgst' => round($gstAmount / 2, 2),
            'sgst' => round($gstAmount / 2, 2),
            'gst_amount' => round($gstAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }
}
