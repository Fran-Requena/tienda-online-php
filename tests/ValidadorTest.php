<?php

require_once __DIR__ . '/../utils/Validador.php';

$casos = [
    'validarNoVacio' => [
        ['hola', true], ['  hola  ', true], ['0', true],
        ['', false], [" \t\n\r", false], [null, false], [[], false],
    ],
    'validarEmail' => [
        ['persona@example.com', true], ['persona+tienda@example.org', true],
        ['', false], ['persona@', false], ['@example.com', false],
        ['persona.example.com', false], [' persona@example.com ', false],
        ["persona@example.com\n", false], [null, false], [[], false],
    ],
    'validarDni' => [
        ['12345678Z', true], ['12345678z', true], ['00000000T', true],
        ['00000001R', true], ['00000022E', true], ['00000023T', true],
        ['12345678A', false], ['1234567Z', false], ['123456789Z', false],
        ['X1234567L', false], ['12345678ZZ', false], ['12345678', false],
        [' 12345678Z', false], ["12345678Z\n", false],
        ['１２３４５６７８Z', false], ['', false], [null, false], [[], false],
    ],
    'validarPais' => [
        ['España', true], ['Portugal', true], ['Espana', false],
        ['españa', false], ['portugal', false], ['España ', false],
        ['Francia', false], ['', false], [true, false], [null, false], [[], false],
    ],
    'validarCodigoPostal' => [
        ['28001', true], ['01001', true], ['1000', true], ['0001', true],
        ['1000001', true], ['0000001', true], ['123', false], ['123456', false],
        ['12345678', false], ['1000-001', false], ['12a45', false],
        [' 28001', false], ["28001\n", false], ['１２３４５', false],
        ['', false], [null, false], [[], false], [28001, false],
    ],
];

$totalCasos = 0;
foreach ($casos as $metodo => $pruebas) {
    foreach ($pruebas as [$valor, $resultadoEsperado]) {
        if (Validador::$metodo($valor) !== $resultadoEsperado) {
            throw new RuntimeException('Fallo en ' . $metodo . ' con ' . var_export($valor, true));
        }
        $totalCasos++;
    }
}

echo 'OK: ' . $totalCasos . " validaciones comprobadas.\n";
