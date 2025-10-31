<?php
// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die(__('Du har ikke tilgang til denne siden.', 'fiskedagbok'));
}

$credentials_updated = false;
$credentials_cleared = false;

if (isset($_POST['fiskedagbok_frost_credentials_nonce']) && wp_verify_nonce($_POST['fiskedagbok_frost_credentials_nonce'], 'fiskedagbok_update_frost_credentials')) {
    $client_id = isset($_POST['frost_client_id']) ? sanitize_text_field(wp_unslash($_POST['frost_client_id'])) : '';
    $client_secret_input = isset($_POST['frost_client_secret']) ? trim(wp_unslash($_POST['frost_client_secret'])) : '';
    $clear_secret = isset($_POST['frost_clear_secret']);

    update_option('fiskedagbok_frost_client_id', $client_id);

    if ($clear_secret) {
        delete_option('fiskedagbok_frost_client_secret');
        $credentials_cleared = true;
    } elseif ($client_secret_input !== '') {
        update_option('fiskedagbok_frost_client_secret', sanitize_text_field($client_secret_input));
        $credentials_updated = true;
    } else {
        $credentials_updated = true;
    }
}

$frost_client_id = get_option('fiskedagbok_frost_client_id');
$frost_client_secret = get_option('fiskedagbok_frost_client_secret');
$has_secret = !empty($frost_client_secret);

?>

<div class="wrap">
    <h1>Værdata API</h1>
    <p>Denne siden lar deg koble Fiskedagbok-pluginen til observasjons-API-et til <a href="https://frost.met.no" target="_blank" rel="noopener noreferrer">frost.met.no</a> slik at værdata hentes fra historiske målinger.</p>

    <?php if ($credentials_updated && !$credentials_cleared): ?>
        <div class="notice notice-success is-dismissible"><p>Frost API-innstillinger er lagret.</p></div>
    <?php endif; ?>

    <?php if ($credentials_cleared): ?>
        <div class="notice notice-warning is-dismissible"><p>Hemmelig nøkkel er fjernet. Legg inn ny nøkkel for å hente data fra Frost.</p></div>
    <?php endif; ?>

    <form method="post" style="max-width: 560px;">
        <?php wp_nonce_field('fiskedagbok_update_frost_credentials', 'fiskedagbok_frost_credentials_nonce'); ?>
        <h2>Frost.met.no API-tilkobling</h2>
        <ol>
            <li><a href="https://frost.met.no/auth/requestCredentials.html" target="_blank" rel="noopener noreferrer">Registrer en Frost-bruker</a> (gratis) og bekreft e-postadressen din.</li>
            <li>Etter innlogging finner du <strong>Client ID</strong> og <strong>Client secret</strong> på kontosiden. Kopier verdiene og lim dem inn nedenfor.</li>
            <li>Lagre innstillingene, og trykk deretter «Test Frost-tilkobling» for å bekrefte at historiske data hentes.</li>
        </ol>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="frost_client_id">Client ID</label></th>
                    <td>
                        <input type="text" class="regular-text" id="frost_client_id" name="frost_client_id" value="<?php echo esc_attr($frost_client_id); ?>" autocomplete="off">
                        <p class="description">Eks: <code>yourname@example.com</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="frost_client_secret">Client secret</label></th>
                    <td>
                        <input type="password" class="regular-text" id="frost_client_secret" name="frost_client_secret" value="" autocomplete="new-password" placeholder="Oppgi ny nøkkel for å oppdatere">
                        <p class="description">
                            <?php if ($has_secret): ?>
                                Status: <strong>lagret</strong> (skjult). La feltet stå tomt for å beholde verdien.
                            <?php else: ?>
                                Status: <strong>ikke satt</strong>.
                            <?php endif; ?>
                        </p>
                        <label><input type="checkbox" name="frost_clear_secret" value="1"> Fjern lagret hemmelig nøkkel</label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">Lagre Frost-innstillinger</button>
        </p>
    </form>

    <hr>

    <h2>Test API-tilkobling</h2>
    <p>Trykk på testen for å hente værdata. Hvis Frost-legitimasjonen er gyldig og datoen har historiske observasjoner, vil svaret komme fra frost.met.no.</p>
    <p>
        <button id="fiskedagbok-test-weather" class="button">Kjør test</button>
    </p>
    <div id="fiskedagbok-weather-test-result" style="max-width: 600px;"></div>
</div>

<script>
(function($){
    function renderResult(container, success, title, details) {
        var klass = success ? 'background: #d1e7dd; border: 1px solid #badbcc;' : 'background: #f8d7da; border: 1px solid #f5c2c7;';
        var html = '<div style="' + klass + ' padding: 12px 16px; border-radius: 4px; margin-top: 12px;">' +
            '<h3 style="margin-top:0;">' + title + '</h3>' +
            '<div>' + details + '</div>' +
        '</div>';
        container.html(html);
    }

    function testWeather(source) {
        var $result = $('#fiskedagbok-weather-test-result');
        $result.html('<p>Tester ' + source + '...</p>');
        $.post(ajaxurl, {
            action: 'test_weather_api',
            nonce: '<?php echo wp_create_nonce('fiskedagbok_nonce'); ?>'
        }).done(function(response){
            if (!response || !response.success || !response.data) {
                renderResult($result, false, 'Feil', '<p>Kunne ikke hente data. Kontroller nøkler og prøv igjen.</p>');
                return;
            }
            var weather = response.data;
            var details = '<p><strong>Kilde:</strong> ' + (weather.source || 'ukjent') + '</p>' +
                          '<p><strong>Stasjon:</strong> ' + (weather.station || 'ukjent') + '</p>' +
                          '<p><strong>Temperatur:</strong> ' + weather.temperature + ' °C</p>' +
                          '<p><strong>Nedbør:</strong> ' + (weather.precipitation !== null ? weather.precipitation + ' mm' : 'ukjent') + '</p>' +
                          '<p><strong>Observasjonstid:</strong> ' + (weather.observation_time || weather.date || 'ukjent') + '</p>';
            renderResult($result, true, 'Test vellykket', details);
        }).fail(function(){
            renderResult($result, false, 'Teknisk feil', '<p>Serveren svarte ikke. Prøv på nytt.</p>');
        });
    }

    $('#fiskedagbok-test-weather').on('click', function(e){
        e.preventDefault();
        testWeather('værdata');
    });
})(jQuery);
</script>
