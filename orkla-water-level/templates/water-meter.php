<?php
if (!defined('ABSPATH')) {
    exit;
}

$has_measurements = !empty($station_cards);
$reference_label = $max_reference ? number_format_i18n($max_reference, 1) : null;
?>

<div class="orkla-meter-wrapper">
    <div class="orkla-meter-header">
        <div class="orkla-meter-title">
            <span class="orkla-meter-title-icon" aria-hidden="true">💧</span>
            <h3><?php esc_html_e('Vannføring akkurat nå', 'orkla-water-level'); ?></h3>
        </div>
        <?php if (!empty($updated_at)) : ?>
            <div class="orkla-meter-updated">
                <span class="orkla-meter-updated-primary">
                    <?php printf(esc_html__('Oppdatert %s', 'orkla-water-level'), esc_html($updated_at)); ?>
                </span>
                <?php if (!empty($updated_relative)) : ?>
                    <span class="orkla-meter-updated-secondary">
                        <?php printf(esc_html__('(~%s siden)', 'orkla-water-level'), esc_html($updated_relative)); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($has_measurements) : ?>
        <div class="orkla-meter-grid">
            <?php foreach ($station_cards as $card) : ?>
                <article class="orkla-meter-card orkla-meter-card--<?php echo esc_attr($card['slug']); ?>">
                    <div class="orkla-meter-card-head">
                        <div class="orkla-meter-card-labels">
                            <span class="orkla-meter-card-title"><?php echo esc_html($card['label']); ?></span>
                            <?php if (!empty($card['description'])) : ?>
                                <span class="orkla-meter-card-description"><?php echo esc_html($card['description']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="orkla-meter-card-visual">
                        <span class="orkla-meter-disc<?php echo $card['value_formatted'] === null ? ' orkla-meter-disc--empty' : ''; ?>" style="--accent: <?php echo esc_attr($card['color']); ?>; --fill-target: <?php echo esc_attr($card['percent_style']); ?>%;">
                            <span class="orkla-meter-disc-inner">
                                <span class="orkla-meter-disc-liquid">
                                    <span class="orkla-meter-disc-wave orkla-meter-disc-wave--front"></span>
                                    <span class="orkla-meter-disc-wave orkla-meter-disc-wave--back"></span>
                                </span>
                                <?php if ($card['value_formatted'] !== null) : ?>
                                    <span class="orkla-meter-disc-reading">
                                        <span class="orkla-meter-disc-value"><?php echo esc_html($card['value_formatted']); ?></span>
                                        <span class="orkla-meter-disc-unit">m³/s</span>
                                    </span>
                                <?php else : ?>
                                    <span class="orkla-meter-disc-empty"><?php esc_html_e('Ingen data', 'orkla-water-level'); ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p class="orkla-meter-empty"><?php esc_html_e('Ingen vannstandsmålinger kunne vises.', 'orkla-water-level'); ?></p>
    <?php endif; ?>

    <?php if ($show_temperature && $temperature_card) : ?>
        <section class="orkla-thermometer-card">
            <div class="orkla-thermometer-head">
                <span class="orkla-thermometer-icon" aria-hidden="true"><?php echo esc_html($temperature_card['icon']); ?></span>
                <div class="orkla-thermometer-labels">
                    <span class="orkla-thermometer-title"><?php echo esc_html($temperature_card['label']); ?></span>
                    <?php if ($temperature_card['value_formatted'] !== null) : ?>
                        <span class="orkla-thermometer-value"><?php echo esc_html($temperature_card['value_formatted']); ?> <?php echo esc_html($temperature_card['unit']); ?></span>
                    <?php else : ?>
                        <span class="orkla-thermometer-missing"><?php esc_html_e('Ingen temperatur registrert', 'orkla-water-level'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="orkla-thermometer-body">
                <div class="orkla-thermometer-column" style="--accent: <?php echo esc_attr($temperature_card['color']); ?>;">
                    <div class="orkla-thermometer-mercury" style="--fill-target: <?php echo esc_attr($temperature_card['percent_style']); ?>%;">
                        <div class="orkla-thermometer-wave orkla-thermometer-wave--front"></div>
                        <div class="orkla-thermometer-wave orkla-thermometer-wave--back"></div>
                    </div>
                    <div class="orkla-thermometer-scale" aria-hidden="true">
                        <span class="orkla-thermometer-scale-min"><?php echo esc_html(number_format_i18n($temperature_card['min_value'], 0)); ?>°</span>
                        <span class="orkla-thermometer-scale-max"><?php echo esc_html(number_format_i18n($temperature_card['max_value'], 0)); ?>°</span>
                    </div>
                    <div class="orkla-thermometer-bulb">
                        <span class="orkla-thermometer-bulb-glow"></span>
                    </div>
                </div>
                <div class="orkla-thermometer-meta">
                    <?php if ($temperature_card['value_formatted'] !== null) : ?>
                        <span class="orkla-thermometer-percent"><?php echo esc_html($temperature_card['percent_label']); ?>%</span>
                        <span class="orkla-thermometer-range">
                            <?php printf(
                                esc_html__('Skalert mellom %1$s°C og %2$s°C', 'orkla-water-level'),
                                esc_html(number_format_i18n($temperature_card['min_value'], 0)),
                                esc_html(number_format_i18n($temperature_card['max_value'], 0))
                            ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php elseif ($show_temperature) : ?>
        <section class="orkla-thermometer-card orkla-thermometer-card--empty">
            <p><?php esc_html_e('Ingen temperaturdata er tilgjengelig akkurat nå.', 'orkla-water-level'); ?></p>
        </section>
    <?php endif; ?>
</div>
