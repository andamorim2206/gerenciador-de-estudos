<?php
/**
 * Desenho do neurônio. Cada estágio acrescenta uma camada ao anterior.
 * @var int $estagio
 * @var array $equipados  slugs por categoria (skin, cenário, efeito), viram classes CSS
 * @var bool $ativo       pulso leve durante uma sessão em andamento
 */
$ativo ??= false;
$classes = array_merge(['neuronio', 'estagio-' . $estagio], array_values($equipados), $ativo ? ['ativo'] : []);
$axonio = 'M98 104 C122 106 140 112 166 132';
$temFaiscas = in_array('efeito-faiscas', $equipados, true);
?>
<div class="<?= e(implode(' ', $classes)) ?>">
    <svg viewBox="0 0 210 200" role="img" aria-label="Neurônio no estágio <?= $estagio ?>">
        <defs>
            <linearGradient id="neuronio-aurora" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#2dd4bf"/>
                <stop offset="1" stop-color="#a78bfa"/>
            </linearGradient>
        </defs>

        <?php if ($estagio >= 6): ?>
            <g class="rede">
                <path d="M181 126 Q196 92 172 64"/>
                <path d="M175 147 Q170 166 156 172"/>
                <path d="M30 112 Q14 140 26 168"/>
                <circle cx="168" cy="56" r="9"/>
                <circle cx="150" cy="176" r="7"/>
                <circle cx="28" cy="176" r="7"/>
                <path class="fino" d="M162 49 L154 38 M175 50 L184 40 M176 60 L190 62"/>
                <path class="fino" d="M144 181 L136 190 M156 181 L162 192"/>
                <path class="fino" d="M22 182 L14 190 M34 183 L40 193"/>
            </g>
        <?php endif; ?>

        <?php if ($estagio >= 1): ?>
            <g class="dendritos">
                <path d="M66 89 Q52 72 41 56"/>
                <path d="M63 104 Q46 108 30 112"/>
                <path d="M70 116 Q59 133 48 148"/>
                <path d="M83 83 Q85 66 79 47"/>
            </g>
        <?php endif; ?>

        <?php if ($estagio >= 2): ?>
            <g class="ramos">
                <path d="M41 56 L28 49 M41 56 L38 40"/>
                <path d="M30 112 L17 104 M30 112 L19 124"/>
                <path d="M48 148 L35 156 M48 148 L52 164"/>
                <path d="M79 47 L68 36 M79 47 L89 34"/>
                <path class="fino" d="M28 49 L18 50 M38 40 L33 30 M17 104 L9 98 M35 156 L27 152 M68 36 L66 26 M89 34 L98 30"/>
            </g>
        <?php endif; ?>

        <?php if ($estagio >= 3): ?>
            <path class="axonio" d="<?= $axonio ?>"/>
        <?php endif; ?>

        <?php if ($estagio >= 4): ?>
            <g class="mielina">
                <rect x="104" y="101" width="13" height="8" rx="4" transform="rotate(4 110 105)"/>
                <rect x="121" y="104" width="13" height="8" rx="4" transform="rotate(10 127 108)"/>
                <rect x="137" y="109" width="13" height="8" rx="4" transform="rotate(22 143 113)"/>
                <rect x="151" y="117" width="13" height="8" rx="4" transform="rotate(33 157 121)"/>
            </g>
        <?php endif; ?>

        <?php if ($estagio >= 5): ?>
            <g class="terminais">
                <path d="M166 132 L181 126 M166 132 L183 140 M166 132 L175 147"/>
                <circle cx="181" cy="126" r="3.5"/>
                <circle cx="183" cy="140" r="3.5"/>
                <circle cx="175" cy="147" r="3.5"/>
            </g>
        <?php endif; ?>

        <g class="soma">
            <circle cx="80" cy="100" r="19"/>
            <circle class="nucleo" cx="80" cy="100" r="6.5"/>
        </g>

        <?php if ($temFaiscas): ?>
            <g class="faiscas">
                <?php foreach ([0, 0.55, 1.1] as $atraso): ?>
                    <?php // Atraso negativo: a faísca já começa em movimento, em vez de parada na origem. ?>
                    <circle r="2.6">
                        <animateMotion dur="1.65s" begin="-<?= $atraso ?>s" repeatCount="indefinite"
                                       path="<?= $estagio >= 3 ? $axonio : 'M80 60 A40 40 0 1 1 79.9 60' ?>"/>
                    </circle>
                <?php endforeach; ?>
            </g>
        <?php endif; ?>
    </svg>
</div>
