<?php
declare(strict_types=1);

/**
 * Безопасный вывод данных для предотвращения XSS атак.
 */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * =====================================================
 * MQ-2: Преобразование показаний ADC в ppm (физически корректная модель)
 * =====================================================
 *
 * Логарифмическая аппроксимация на основе даташита:
 * log10(Rs/R0) = a * log10(ppm) + b
 *
 * @param int    $adc    Значение ADC (0–1023)
 * @param string $gas    Тип газа (CO | CH4 | LPG | H2)
 * @param float  $Vc     Напряжение питания
 * @param float  $RL     Сопротивление нагрузки
 * @param float  $R0     Базовое сопротивление
 *
 * @return float ppm    Концентрация газа в ppm
 */
function mq2_ppm_from_adc(
    int $adc,
    string $gas = 'CO',
    float $Vc = 5.0,
    float $RL = 5.0,
    float $R0 = 9.83
): float {
    if ($adc <= 0 || $adc > 1023) {
        return 0.0;  // Возвращаем 0, если значение ADC выходит за допустимые пределы
    }

    // Коэффициенты для разных типов газов из даташита MQ-2
    $coeffs = [
        'CO'  => ['a' => -0.77, 'b' => 1.70],
        'CH4' => ['a' => -0.38, 'b' => 1.50],
        'LPG' => ['a' => -0.45, 'b' => 1.30],
        'H2'  => ['a' => -0.50, 'b' => 1.20],
    ];

    // Проверяем, есть ли коэффициенты для заданного газа
    if (!isset($coeffs[$gas])) {
        return 0.0;  // Если нет - возвращаем 0
    }

    // Перевод ADC в напряжение
    $Vout = ($adc / 1023.0) * $Vc;
    if ($Vout < 0.01) {
        return 0.0;
    }

    // Рассчитываем сопротивление Rs на основе напряжения
    $Rs = $RL * (($Vc - $Vout) / $Vout);

    // Рассчитываем отношение Rs/R0
    $ratio = $Rs / $R0;

    // Преобразуем это отношение в концентрацию газа (ppm)
    $a = $coeffs[$gas]['a'];
    $b = $coeffs[$gas]['b'];

    $ppm = pow(10, (log10($ratio) - $b) / $a);

    return round(max($ppm, 0), 1);  // Возвращаем концентрацию в ppm с точностью до 1
}

/**
 * =====================================================
 * Совместимость с предыдущим кодом
 * Возвращает концентрацию газа в ПРОЦЕНТАХ
 * (1% = 10 000 ppm)
 * =====================================================
 */
function calculate_gas_concentration(int $adc_value, string $gas_type = 'CO'): float {
    // Получаем ppm и переводим в проценты
    $ppm = mq2_ppm_from_adc($adc_value, $gas_type);
    return round($ppm / 10000, 4);  // Концентрация в процентах (1% = 10,000 ppm)
}

/**
 * =====================================================
 * Функции для flash-сообщений
 * =====================================================
 */
function flash_set(string $type, string $msg): void { 
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; 
}

function flash_get(): ?array { 
    if (empty($_SESSION['flash'])) return null; 
    $f = $_SESSION['flash']; 
    unset($_SESSION['flash']); 
    return $f; 
}

function redirect(string $path): void { 
    header('Location: ' . $path); 
    exit; 
}
?>
