<?php
/**
* PagSeguro Perfex CRM
* @version 1.0
* @autor Allan Lima <contato@allanlima.com>
* @website www.allanlima.com
*/

defined('BASEPATH') or exit('No direct script access allowed');

class Pagseguro_gateway extends App_gateway{
    public function __construct(){

         /**
         * Call App_gateway __construct function
         */
        parent::__construct();
        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('pagseguro');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('PagSeguro');

        /**
         * Add gateway settings
        */
        $this->setSettings([
            [
                'name'      => 'pagseguro_email',
                'encrypted' => true,
                'label'     => 'E-mail',
            ],
			[
                'name'      => 'pagseguro_token',
                'encrypted' => true,
                'label'     => 'Token',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'currency',
                'default_value' => 'BRL',
                // 'field_attributes'=>['disabled'=>true],
            ],
            [
                'name'          => 'test_mode_enabled',
                'type'          => 'yes_no',
                'default_value' => 1,
                'label'         => 'settings_paymentmethod_testing_mode',
            ],
        ]);

        /**
         * REQUIRED
         * Hook gateway with other online payment modes
         */
        add_action('before_add_online_payment_modes', [ $this, 'initMode' ]);
    }

    public function process_payment($data){
		$payment = $this->create_payment($data);
		redirect($payment);
    }

	private function create_payment($data = null){
		if(empty($data)){ return; }
		
		if($this->getSetting('test_mode_enabled') == 1){
			$environment = 'sandbox';
		}else{
			$environment = 'production';
		}
		
		\PagSeguro\Library::initialize();
		\PagSeguro\Library::cmsVersion()->setName("Perfex CRM")->setRelease("2.2.1");
		\PagSeguro\Library::moduleVersion()->setName("Perfex CRM")->setRelease("2.2.1");
		\PagSeguro\Configuration\Configure::setEnvironment($environment);
		\PagSeguro\Configuration\Configure::setAccountCredentials($this->decryptSetting('pagseguro_email'), $this->decryptSetting('pagseguro_token'));
		\PagSeguro\Configuration\Configure::setCharset('UTF-8');
		
		$payment = new \PagSeguro\Domains\Requests\Payment();

		$vat = $data["invoice"]->client->vat;
		$vat = preg_replace("/[^0-9]/", "", $vat);
		
		$phone_number = $data["invoice"]->client->phonenumber;
		$phone_number = preg_replace("/[^0-9]/", "", $phone_number);
		
		$ddd_number = substr($phone_number, 0, 2);
		$phone_number = substr($phone_number, 2);
		
		$payment->setSender()->setName($data["invoice"]->client->company);
		$payment->setSender()->setPhone()->withParameters($ddd_number, $phone_number);
		if(strlen($vat) == 11){
			$payment->setSender()->setDocument()->withParameters('CPF', $vat);
		}elseif(strlen($vat) == 14){
			$payment->setSender()->setDocument()->withParameters('CNPJ', $vat);
		}
		
		$payment->setCurrency('BRL');
		$payment->setReference(format_invoice_number($data['invoice']->id));
		$payment->setRedirectUrl(site_url('/invoice/' . $data['invoiceid'] . '/' . $data['hash']));
		$payment->setNotificationUrl(site_url('gateways/pagseguro/callback?invoiceid=' . $data['invoice']->id));
		
		$invoiceNumber = format_invoice_number($data['invoice']->id);
		$description = str_replace('{invoice_number}', $invoiceNumber, $this->getSetting('description_dashboard'));
		
		$payment->addItems()->withParameters($data['invoiceid'], $description, 1, number_format($data['amount'], 2, '.', ''));

		logActivity('Pagseguro: Uma nova transação de pagamento foi gerada para a fatura ' . $data['invoice']->id . '.');
		
		return $payment->register(\PagSeguro\Configuration\Configure::getAccountCredentials());
	}
	
	public function get_payment($transactionCode = null){
		if($this->getSetting('test_mode_enabled') == 1){
			$environment = 'sandbox';
		}else{
			$environment = 'production';
		}
		
		\PagSeguro\Library::initialize();
		\PagSeguro\Library::cmsVersion()->setName("Perfex CRM")->setRelease("2.2.1");
		\PagSeguro\Library::moduleVersion()->setName("Perfex CRM")->setRelease("2.2.1");
		\PagSeguro\Configuration\Configure::setEnvironment($environment);
		\PagSeguro\Configuration\Configure::setAccountCredentials($this->decryptSetting('pagseguro_email'), $this->decryptSetting('pagseguro_token'));
		\PagSeguro\Configuration\Configure::setCharset('UTF-8');
		
		if(!empty($transactionCode)){
			$response = \PagSeguro\Services\Transactions\Search\Code::search(\PagSeguro\Configuration\Configure::getAccountCredentials(), $transactionCode);
    	}else{
			return null;
		}
		
		return $response;
	}
	
	public function add_token($invoiceid = null, $token = null){
		if(empty($invoiceid) && empty($token)){ return false; }
		
		$this->ci->db->where('id', $invoiceid);
		$this->ci->db->update('tblinvoices', [
			'token' => $token,
		]);
		
		return true;
	}
}
