<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class PhoneSearch
{
    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public static function applyToCustomerQuery(Builder $query, string $term): Builder
    {
        return self::applyToColumn($query, 'phone_number', $term);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToColumn(Builder $query, string $column, string $term): Builder
    {
        $digits = self::normalizedDigits($term);

        if ($digits !== '' && strlen($digits) >= 10) {
            return $query->where($column, $digits);
        }

        if ($digits !== '' && strlen($digits) >= 3) {
            return $query->where($column, 'like', $digits.'%');
        }

        return $query->where($column, 'like', '%'.$term.'%');
    }

    /**
     * @return Builder<Customer>
     */
    public static function matchingCustomerIdsQuery(string $term): Builder
    {
        return Customer::query()
            ->where(fn (Builder $query): Builder => self::applyToCustomerQuery($query, $term))
            ->select('id');
    }

    public static function normalizedDigits(string $term): string
    {
        return preg_replace('/\D+/', '', $term) ?? '';
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public static function applyToInvoiceQuery(Builder $query, string $term): Builder
    {
        $digits = self::normalizedDigits($term);

        return $query->where(function (Builder $query) use ($term, $digits): void {
            if ($digits !== '' && strlen($digits) >= 10) {
                $query->where('phone_number', $digits);
            } elseif ($digits !== '' && strlen($digits) >= 3) {
                $query->where('phone_number', 'like', $digits.'%');
            } else {
                $query->where('phone_number', 'like', '%'.$term.'%');
            }

            if (ctype_digit($term)) {
                $query->orWhere('invoice_number', 'like', $term.'%');
            } else {
                $query->orWhere('invoice_number', 'like', '%'.$term.'%');
            }
        });
    }
}
