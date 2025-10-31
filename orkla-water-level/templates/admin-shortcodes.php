<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap orkla-shortcodes">
    <h1><?php esc_html_e('Tilgjengelige shortcodes', 'orkla-water-level'); ?></h1>
    <p class="description">
        <?php esc_html_e('Bruk disse shortcode-kodene for å vise vannføringsdata hvor som helst på nettstedet. Kopier eksempelet direkte inn i editoren, eller tilpass attributtene etter behov.', 'orkla-water-level'); ?>
    </p>

    <?php if (!empty($shortcodes)) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Shortcode', 'orkla-water-level'); ?></th>
                    <th scope="col"><?php esc_html_e('Beskrivelse', 'orkla-water-level'); ?></th>
                    <th scope="col"><?php esc_html_e('Attributter', 'orkla-water-level'); ?></th>
                    <th scope="col"><?php esc_html_e('Eksempel', 'orkla-water-level'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shortcodes as $definition) : ?>
                    <tr>
                        <td>
                            <code>[<?php echo esc_html($definition['tag']); ?>]</code>
                        </td>
                        <td><?php echo esc_html($definition['description']); ?></td>
                        <td>
                            <?php if (!empty($definition['attributes'])) : ?>
                                <ul class="orkla-shortcode-attributes">
                                    <?php foreach ($definition['attributes'] as $attribute => $help) : ?>
                                        <li>
                                            <code><?php echo esc_html($attribute); ?></code>
                                            <span>— <?php echo esc_html($help); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <em><?php esc_html_e('Ingen ekstra attributter tilgjengelig.', 'orkla-water-level'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($definition['example'])) : ?>
                                <code><?php echo esc_html($definition['example']); ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e('Ingen shortcodes registrert.', 'orkla-water-level'); ?></p>
    <?php endif; ?>
</div>
