<?php /** @noinspection PhpUnused */

namespace Drupal\esn_cyprus_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\esn_cyprus_core\Config\CoreSettings;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SettingsForm extends ConfigFormBase
{
    /**
     * {@inheritDoc}
     */
    protected function getEditableConfigNames(): array
    {
        return [CoreSettings::CONFIG_NAME];
    }

    /**
     * {@inheritDoc}
     */
    public function getFormId(): string
    {
        return 'esn_cyprus_core_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $coreSettings = new CoreSettings($this->configFactory);

        $form['#prefix'] = '<div id="esn-core-settings-wrapper">';
        $form['#suffix'] = '</div>';

        $form['switches'] = [
            '#type' => 'details',
            '#title' => $this->t('Enable / Disable Integrations'),
            '#open' => TRUE
        ];

        $form['switches']['switch_google'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Google Integration'),
            '#default_value' => $form_state->getValue('switch_google') ?? $coreSettings->getGoogleSwitch(),
            '#ajax' => [
                'callback' => '::switchToggle',
                'wrapper' => 'esn-core-settings-wrapper'
            ]
        ];

        $form['switches']['switch_apple'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Apple Integration'),
            '#default_value' => $form_state->getValue('switch_apple') ?? $coreSettings->getAppleSwitch(),
            '#ajax' => [
                'callback' => '::switchToggle',
                'wrapper' => 'esn-core-settings-wrapper'
            ]
        ];

        $form['email'] = [
            '#type' => 'details',
            '#title' => $this->t('Email Settings'),
            '#description' => $this->t('Configuration for the parameters needed for the Email Manager.'),
            '#open' => true
        ];

        $form['email']['email_address'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Sender Email Address'),
            '#description' => $this->t('Enter the email address from where the emails will be sent.'),
            '#default_value' => $coreSettings->getEmailAddress(),
            '#required' => true
        ];

        $form['email']['email_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Sender Email Name'),
            '#description' => $this->t('Enter the user-friendly name from where the emails will be sent.'),
            '#default_value' => $coreSettings->getEmailName(),
            '#required' => true
        ];

        $form['email']['email_footer'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Email Footer'),
            '#description' => $this->t('Enter the HTML for the footer of the emails to be sent.'),
            '#default_value' => $coreSettings->getEmailFooter(),
            '#required' => true
        ];

        $form['google'] = [
            '#type' => 'details',
            '#title' => $this->t('Google Settings'),
            '#description' => $this->t('Configuration for the Google Integration.'),
            '#open' => $form_state->getValue('switch_google') ?? $coreSettings->getGoogleSwitch()
        ];

        if ($email = $coreSettings->getGoogleClientEmail()) {
            $form['google']['current_status'] = [
                '#markup' => '<div class="alert alert-success">' .
                    $this->t('Currently connected as: <strong>@email</strong>', ['@email' => $email]) .
                    '</div>',
            ];
        } else {
            $form['google']['current_status'] = [
                '#markup' => '<div class="alert alert-warning">' .
                    $this->t('No Service Account credentials configured.') .
                    '</div>',
            ];
        }

        $form['google']['google_json_key_file'] = [
            '#type' => 'file',
            '#title' => $this->t('Upload Google Service Account JSON'),
            '#description' => $this->t('Upload the .json file you downloaded from Google Console. The system will extract the keys and discard the file.'),
            '#attributes' => [
                'accept' => '.json',
            ],
            '#disabled' => !($form_state->getValue('switch_google') ?? $coreSettings->getGoogleSwitch()),
            '#required' => empty($email) && ($form_state->getValue('switch_google') ?? $coreSettings->getGoogleSwitch())
        ];

        $form['google']['google_issuer_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Issuer ID'),
            '#description' => $this->t('The Issuer ID from the Google Wallet Console.'),
            '#default_value' => $coreSettings->getGoogleIssuerID(),
            '#disabled' => !($form_state->getValue('switch_google') ?? $coreSettings->getGoogleSwitch()),
        ];

        $form['apple'] = [
            '#type' => 'details',
            '#title' => $this->t('Apple Wallet Settings'),
            '#description' => $this->t('Configuration for the Apple Wallet Service.'),
            '#open' => $form_state->getValue('switch_apple') ?? $coreSettings->getAppleSwitch()
        ];

        $form['apple']['apple_team_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Apple Team ID'),
            '#description' => $this->t('Your Apple Team ID.'),
            '#default_value' => $coreSettings->getAppleTeamID(),
            '#disabled' => !($form_state->getValue('switch_apple') ?? $coreSettings->getAppleSwitch()),
            '#required' => $form_state->getValue('switch_apple') ?? $coreSettings->getAppleSwitch()
        ];

        return parent::buildForm($form, $form_state);
    }

    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        parent::validateForm($form, $form_state);

        $allFiles = $this->getRequest()->files->get('files', []);

        /** @var UploadedFile $googleFile */
        $googleFile = $allFiles['google_json_key_file'] ?? NULL;
        if ($googleFile instanceof UploadedFile) {
            if ($googleFile->isValid()) {
                $content = file_get_contents($googleFile->getRealPath());
                $json = json_decode($content, TRUE);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $form_state->setErrorByName('google_json_key_file', $this->t('The uploaded file is not valid JSON.'));
                    return;
                }

                if (empty($json['client_email']) || empty($json['private_key'])) {
                    $form_state->setErrorByName('google_json_key_file', $this->t('The JSON file does not contain "client_email" or "private_key". Are you sure this is a Service Account key file?'));
                    return;
                }

                $form_state->set('parsed_google_credentials', $json);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $coreSettings = new CoreSettings($this->configFactory, true);

        $coreSettings
            ->setGoogleSwitch($form_state->getValue('switch_google'))
            ->setAppleSwitch($form_state->getValue('switch_apple'))
            ->setEmailAddress($form_state->getValue('email_address'))
            ->setEmailName($form_state->getValue('email_name'))
            ->setEmailFooter($form_state->getValue('email_footer'))
            ->setGoogleIssuerID($form_state->getValue('google_issuer_id'))
            ->setAppleTeamID($form_state->getValue('apple_team_id'));

        if ($googleCredentials = $form_state->get('parsed_google_credentials')) {
            $coreSettings
                ->setGoogleClientEmail($googleCredentials['client_email'])
                ->setGooglePrivateKey($googleCredentials['private_key'])
                ->setGooglePrivateKeyID($googleCredentials['client_id'] ?? '')
                ->setGoogleProjectID($googleCredentials['project_id'] ?? '')
                ->setGoogleClientID($googleCredentials['client_id'] ?? '');

            $this->messenger()->addStatus($this->t('Credentials updated for @email. Remember to share permissions with this email!', ['@email' => $googleCredentials['client_email']]));
        }

        $coreSettings->save();

        parent::submitForm($form, $form_state);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function switchToggle(array $form, FormStateInterface $form_state): array
    {
        return $form;
    }
}