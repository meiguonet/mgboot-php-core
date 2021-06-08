<?php

namespace mgboot\core\validator;

use DateTime;
use mgboot\bo\DotAccessData;
use mgboot\Cast;
use mgboot\constant\Regexp;
use mgboot\util\ArrayUtils;
use mgboot\util\StringUtils;

final class DataValidator
{
    /**
     * @var RuleChecker[]
     */
    private static array $customRuleCheckers = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function addRuleChecker(RuleChecker $checker): void
    {
        $matched = collect(self::$customRuleCheckers)->first(fn($it) => (
            strtolower($it->getRuleName()) === strtolower($checker->getRuleName())
        ));

        if ($matched instanceof RuleChecker) {
            return;
        }

        self::$customRuleCheckers[] = $checker;
    }
    
    public static function validate(array $data, array $rules, bool $failfast = false): array|string
    {
        if (!ArrayUtils::isStringArray($rules)) {
            return $failfast ? '' : [];
        }

        $bo = DotAccessData::fromArray($data);
        $validateErrors = [];

        foreach ($rules as $rule) {
            $checkOnNotEmpty = false;
            $validator = 'Required';
            $checkValue = '';
            $errorTips = '';

            if (str_contains($rule, '@CheckOnNotEmpty') || str_contains($rule, '@WithNotEmpty')) {
                $checkOnNotEmpty = true;
                $rule = str_replace('@CheckOnNotEmpty', '', $rule);
                $rule = str_replace('@WithNotEmpty', '', $rule);
            }

            if (str_contains($rule, '@msg:')) {
                $errorTips = StringUtils::substringAfterLast($rule, '@');
                $errorTips = preg_replace('/^msg:[\x20\t]*/', '', $errorTips);
                $errorTips = trim($errorTips);
                $rule = StringUtils::substringBeforeLast($rule, '@');
            }

            if (str_contains($rule, '@')) {
                $fieldName = trim(StringUtils::substringBefore($rule, '@'));
                $validator = StringUtils::substringAfter($rule, '@');

                if (str_contains($validator, ':')) {
                    $checkValue = trim(StringUtils::substringAfter($validator, ':'));
                    $validator = trim(StringUtils::substringBefore($validator, ':'));
                }
            } else {
                $fieldName = trim($rule);
            }

            if (empty($errorTips)) {
                $errorTips = match ($validator) {
                    'Mobile' => '不是有效的手机号码',
                    'Email' => '不是有效的邮箱地址',
                    'PasswordTooSimple' => '密码过于简单',
                    'Idcard' => '不是有效的身份证号码',
                    default => '必须填写',
                };
            }

            if (!$failfast && isset($validateErrors[$fieldName])) {
                continue;
            }

            $fieldValue = $bo->getString($fieldName);

            if ($checkOnNotEmpty && $fieldValue === '') {
                continue;
            }

            if ($fieldValue === '') {
                if ($failfast) {
                    return $errorTips;
                }

                $validateErrors[$fieldName] = $errorTips;
                continue;
            }

            if ($validator === 'Required') {
                continue;
            }

            if ($validator === 'EqualsWith') {
                if ($fieldValue !== $bo->getString($checkValue)) {
                    if ($failfast) {
                        return $errorTips;
                    }

                    $validateErrors[$fieldName] = $errorTips;
                }

                continue;
            }

            $func = "is$validator";
            $args = $checkValue === '' ? [$fieldValue] : [$fieldValue, $checkValue];

            if (method_exists(self::class, $func)) {
                if (call_user_func_array([self::class, $func], $args)) {
                    continue;
                }

                if ($failfast) {
                    return $errorTips;
                }

                $validateErrors[$fieldName] = $errorTips;
                continue;
            }

            $checker = collect(self::$customRuleCheckers)->first(fn($it) => (
                strtolower($it->getRuleName()) === strtolower($validator)
            ));

            if (!($checker instanceof RuleChecker) || $checker->check($fieldValue, $checkValue)) {
                continue;
            }

            if ($failfast) {
                return $errorTips;
            }

            $validateErrors[$fieldName] = $errorTips;
        }

        return $failfast ? '' : $validateErrors;
    }

    private static function isDate(string $value): bool
    {
        return StringUtils::isDate($value);
    }

    private static function isDateTime(string $value): bool
    {
        return StringUtils::isDateTime($value);
    }

    private static function isFutureDate(string $value): bool
    {
        if (!self::isDate($value)) {
            return false;
        }

        $d1 = StringUtils::toDateTime($value);

        if (!($d1 instanceof DateTime)) {
            return false;
        }

        $n1 = (int) $d1->format('Ymd');
        $n2 = (int) date('Ymd');
        return $n1 > $n2;
    }

    private static function isPastDate(string $value): bool
    {
        if (!self::isDate($value)) {
            return false;
        }

        $d1 = StringUtils::toDateTime($value);

        if (!($d1 instanceof DateTime)) {
            return false;
        }

        $n1 = (int) $d1->format('Ymd');
        $n2 = (int) date('Ymd');
        return $n2 > $n1;
    }

    private static function isInt(string $value): bool
    {
        return StringUtils::isInt($value);
    }

    private static function isIntEq(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) === Cast::toInt($checkValue);
    }

    private static function isIntNe(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) !== Cast::toInt($checkValue);
    }

    private static function isIntGt(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) > Cast::toInt($checkValue);
    }

    private static function isIntGe(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) >= Cast::toInt($checkValue);
    }

    private static function isIntLt(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) < Cast::toInt($checkValue);
    }

    private static function isIntLe(string $value, string $checkValue): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        return Cast::toInt($value) <= Cast::toInt($checkValue);
    }

    private static function isIntBetween(string $value, string $range): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        $value = Cast::toInt($value);
        $parts = preg_split(Regexp::COMMA_SEP, trim($range));
        $n1 = $n2 = PHP_INT_MIN;

        foreach ($parts as $p) {
            if (!StringUtils::isInt($p)) {
                continue;
            }

            $n3 = (int) $p;

            if ($n1 === PHP_INT_MIN) {
                $n1 = $n3;
            } else if ($n2 === PHP_INT_MIN) {
                $n2 = $n3;
            }
        }

        if ($n1 === PHP_INT_MIN || $n2 === PHP_INT_MIN) {
            return false;
        }

        return $value >= $n1 && $value <= $n2;
    }

    private static function isIntIn(string $value, string $range): bool
    {
        if (!self::isInt($value)) {
            return false;
        }

        $value = Cast::toInt($value);
        $parts = preg_split(Regexp::COMMA_SEP, trim($range));
        $nums = [];

        foreach ($parts as $p) {
            if (!StringUtils::isInt($p)) {
                continue;
            }

            $nums[] = (int) $p;
        }

        return in_array($value, $nums);
    }

    private static function isIntNotIn(string $value, string $range): bool
    {
        return self::isInt($value) && !self::isIntIn($value, $range);
    }

    private static function isFloat(string $value): bool
    {
        return StringUtils::isFloat($value);
    }

    private static function isFloatEq(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 6) === 0;
    }

    private static function isFloatNe(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 6) !== 0;
    }

    private static function isFloatGt(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 6) === 1;
    }

    private static function isFloatGe(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 6) !== -1;
    }

    private static function isFloatLt(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 2) === -1;
    }

    private static function isFloatLe(string $value, string $checkValue): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        return bccomp($value, $checkValue, 2) !== 1;
    }

    private static function isFloatBetween(string $value, string $range): bool
    {
        if (!self::isFloat($value)) {
            return false;
        }

        $value = bcadd($value, 0, 6);
        $parts = preg_split(Regexp::COMMA_SEP, trim($range));
        $n1 = $n2 = PHP_FLOAT_MIN;

        foreach ($parts as $p) {
            if (!StringUtils::isFloat($p)) {
                continue;
            }

            $n3 = bcadd($p, 0, 6);

            if ($n1 === PHP_FLOAT_MIN) {
                $n1 = $n3;
            } else if ($n2 === PHP_FLOAT_MIN) {
                $n2 = $n3;
            }
        }

        if ($n1 === PHP_FLOAT_MIN || $n2 === PHP_FLOAT_MIN) {
            return false;
        }

        return bccomp($value, $n1, 6) !== -1 && bccomp($value, $n2, 6) !== 1;
    }

    private static function isStrEq(string $value, string $checkValue): bool
    {
        return StringUtils::equals($value, $checkValue);
    }

    private static function isStrEqI(string $value, string $checkValue): bool
    {
        return StringUtils::equals($value, $checkValue, true);
    }

    private static function isStrNe(string $value, string $checkValue): bool
    {
        return !StringUtils::equals($value, $checkValue);
    }

    private static function isStrNeI(string $value, string $checkValue): bool
    {
        return !StringUtils::equals($value, $checkValue, true);
    }

    private static function isStrIn(string $value, string $range): bool
    {
        return in_array($value, preg_split(Regexp::COMMA_SEP, trim($range)));
    }

    private static function isStrInI(string $value, string $range): bool
    {
        $parts = preg_split(Regexp::COMMA_SEP, trim($range));
        $parts = array_map(fn($it) => strtolower($it), $parts);
        return in_array(strtolower($value), $parts);
    }

    private static function isStrNotIn(string $value, string $range): bool
    {
        return !self::isStrIn($value, $range);
    }

    private static function isStrNotInI(string $value, string $range): bool
    {
        return !self::isStrInI($value, $range);
    }

    private static function isStrLen(string $value, string $checkValue): bool
    {
        $checkValue = Cast::toInt($checkValue);

        if ($checkValue < 1) {
            return false;
        }

        return mb_strlen($value) === $checkValue;
    }

    private static function isStrLenGt(string $value, string $checkValue): bool
    {
        $checkValue = Cast::toInt($checkValue);

        if ($checkValue < 1) {
            return true;
        }

        return mb_strlen($value) > $checkValue;
    }

    private static function isStrLenGe(string $value, string $checkValue): bool
    {
        $checkValue = Cast::toInt($checkValue);

        if ($checkValue < 1) {
            return true;
        }

        return mb_strlen($value) >= $checkValue;
    }

    private static function isStrLenLt(string $value, string $checkValue): bool
    {
        $checkValue = Cast::toInt($checkValue);

        if ($checkValue < 1) {
            return false;
        }

        return mb_strlen($value) < $checkValue;
    }

    private static function isStrLenLe(string $value, string $checkValue): bool
    {
        $checkValue = Cast::toInt($checkValue);

        if ($checkValue < 1) {
            return false;
        }

        return mb_strlen($value) <= $checkValue;
    }

    private static function isStrLenBetween(string $value, string $range): bool
    {
        $cnt = mb_strlen($value);
        $parts = preg_split(Regexp::COMMA_SEP, trim($range));
        $n1 = $n2 = PHP_INT_MIN;

        foreach ($parts as $p) {
            if (!StringUtils::isInt($p)) {
                continue;
            }

            $n3 = (int) $p;

            if ($n1 === PHP_INT_MIN) {
                $n1 = $n3;
            } else if ($n2 === PHP_INT_MIN) {
                $n2 = $n3;
            }
        }

        if ($n1 === PHP_INT_MIN || $n2 === PHP_INT_MIN) {
            return false;
        }

        return $cnt >= $n1 && $cnt <= $n2;
    }

    private static function isAlphas(string $value): bool
    {
        $n1 = preg_match('/^[A-Za-z]+$/', $value);
        return is_int($n1) && $n1 > 0;
    }

    private static function isNumbers(string $value): bool
    {
        $n1 = preg_match('/^[0-9]+$/', $value);
        return is_int($n1) && $n1 > 0;
    }

    private static function isAlnum(string $value): bool
    {
        $n1 = preg_match('/^[A-Za-z0-9]+$/', $value);
        return is_int($n1) && $n1 > 0;
    }

    private static function isMobile(string $value): bool
    {
        return StringUtils::isNationalMobileNumber($value);
    }

    private static function isEmail(string $value): bool
    {
        return StringUtils::isEmail($value);
    }

    private static function isPasswordTooSimple(string $value): bool
    {
        return !StringUtils::isPasswordTooSimple($value);
    }

    private static function isIdcard(string $value): bool
    {
        $regex1 = '/^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/';

        if (!preg_match($regex1, $value)) {
            return false;
        }

        $s1 = strtolower($value[17]);
        $a1 = ['7', '9', '10', '5', '8', '4', '2', '1', '6', '3', '7', '9', '10', '5', '8', '4', '2'];
        $a2 = ['1', '0', 'x', '9', '8', '7', '6', '5', '4', '3', '2'];
        $n1 = strlen($value) - 1;
        $sum = 0;

        for ($i = 0; $i < $n1; $i++) {
            $n2 = (int)$value[$i];
            $n3 = (int)$a1[$i];
            $sum += $n2 * $n3;
        }

        return $a2[$sum % 11] === $s1;
    }

    private static function isRegexp(string $value, string $checkValue): bool
    {
        $n1 = preg_match($checkValue, $value);

        if (is_int($n1)) {
            return $n1 === 1;
        }

        return is_bool($n1) ? $n1 : false;
    }
}
