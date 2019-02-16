<?php
/**
* PagSeguro Perfex CRM
* @version 1.0
* @autor Allan Lima <contato@allanlima.com>
* @website www.allanlima.com
*/

defined('BASEPATH') or exit('No direct script access allowed');

class Pagseguro extends CRM_Controller {
    public function __construct(){
        parent::__construct();
    }

    public function callback(){
		$invoiceid = $this->input->get('invoiceid');

        $this->db->where('id', $invoiceid);
		$invoice = $this->db->get('tblinvoices')->row();
		
		check_invoice_restrictions($invoiceid, $invoice->hash);
		
		$notificationCode = $this->input->post('notificationCode');
		
		if(count($invoice) > 0){
			if($invoice->status != 2){
				if($this->pagseguro_gateway->getSetting('test_mode_enabled') == 1){
					$environment = 'sandbox';
				}else{
					$environment = 'production';
				}
				
				\PagSeguro\Library::initialize();
				\PagSeguro\Library::cmsVersion()->setName("Perfex CRM")->setRelease("2.2.1");
				\PagSeguro\Library::moduleVersion()->setName("Perfex CRM")->setRelease("2.2.1");
				\PagSeguro\Configuration\Configure::setEnvironment($environment);
				\PagSeguro\Configuration\Configure::setAccountCredentials($this->pagseguro_gateway->decryptSetting('pagseguro_email'), $this->pagseguro_gateway->decryptSetting('pagseguro_token'));
				\PagSeguro\Configuration\Configure::setCharset('UTF-8');
				
				
				if(\PagSeguro\Helpers\Xhr::hasPost()){
					$response = \PagSeguro\Services\Transactions\Notification::check(\PagSeguro\Configuration\Configure::getAccountCredentials());
				} 
				
				$transaction = $this->pagseguro_gateway->get_payment($response->getCode());
				
				$this->pagseguro_gateway->add_token($response->getCode());
				 
				if ($transaction->getStatus() == 3) {
					$paymentcode = $response->getPaymentMethod()->getCode();
					$paymenttype = $response->getPaymentMethod()->getType();
					
					switch($paymentcode){
						case "101":
							$paymentcodetext = "Cartão de crédito Visa";
							break;
						case "102":
							$paymentcodetext = "Cartão de crédito MasterCard";
							break;
						case "103":
							$paymentcodetext = "Cartão de crédito American Express";
							break;
						case "104":
							$paymentcodetext = "Cartão de crédito Diners";
							break;
						case "105":
							$paymentcodetext = "Cartão de crédito Hipercard";
							break;
						case "106":
							$paymentcodetext = "Cartão de crédito Aura";
							break;
						case "107":
							$paymentcodetext = "Cartão de crédito Elo";
							break;
						case "108":
							$paymentcodetext = "Cartão de crédito PLENOCard";
							break;
						case "109":
							$paymentcodetext = "Cartão de crédito PersonalCard";
							break;
						case "110":
							$paymentcodetext = "Cartão de crédito JCB";
							break;
						case "111":
							$paymentcodetext = "Cartão de crédito Discover";
							break;
						case "112":
							$paymentcodetext = "Cartão de crédito BrasilCard";
							break;
						case "113":
							$paymentcodetext = "Cartão de crédito FORTBRASIL";
							break;
						case "114":
							$paymentcodetext = "Cartão de crédito CARDBAN";
							break;
						case "115":
							$paymentcodetext = "Cartão de crédito VALECARD";
							break;
						case "116":
							$paymentcodetext = "Cartão de crédito Cabal";
							break;
						case "117":
							$paymentcodetext = "Cartão de crédito Mais!";
							break;
						case "118":
							$paymentcodetext = "Cartão de crédito Avista";
							break;
						case "119":
							$paymentcodetext = "Cartão de crédito GRANDCARD";
							break;
						case "120":
							$paymentcodetext = "Cartão de crédito Sorocred";
							break;
						case "122":
							$paymentcodetext = "Cartão de crédito Up Policard";
							break;
						case "123":
							$paymentcodetext = "Cartão de crédito Banese Card";
							break;
						case "201":
							$paymentcodetext = "Boleto Bradesco";
							break;
						case "202":
							$paymentcodetext = "Boleto Santander";
							break;
						case "301":
							$paymentcodetext = "Débito online Bradesco";
							break;
						case "302":
							$paymentcodetext = "Débito online Itaú";
							break;
						case "303":
							$paymentcodetext = "Débito online Unibanco";
							break;
						case "304":
							$paymentcodetext = "Débito online Banco do Brasil";
							break;
						case "305":
							$paymentcodetext = "Débito online Banco Real";
							break;
						case "306":
							$paymentcodetext = "Débito online Banrisul";
							break;
						case "307":
							$paymentcodetext = "Débito online HSBC";
							break;
						case "401":
							$paymentcodetext = "Saldo PagSeguro";
							break;
						case "501":
							$paymentcodetext = "Oi Paggo";
							break;
						case "701":
							$paymentcodetext = "Depósito em conta - Banco do Brasil";
							break;
					}
					
					switch($paymenttype){
						case "1":
							$paymentmodetext = "Cartão de Crédito";
							break;
						case "2":
							$paymentmodetext = "Boleto";
							break;
						case "3":
							$paymentmodetext = "Débito online (TEF)";
							break;
						case "4":
							$paymentmodetext = "Saldo PagSeguro";
							break;
						case "5":
							$paymentmodetext = "Oi Paggo";
							break;
						case "7":
							$paymentmodetext = "Depósito em conta";
							break;
					}

					$paymentmethod = $paymentmodetext . " - " . $paymentcodetext;
					
					$this->pagseguro_gateway->addPayment(
					[
					  'amount'        => $invoice->total,
					  'invoiceid'     => $invoiceid,
					  'paymentmode'     => 'pagseguro',
					  'paymentmethod' => $paymentmethod,
					  'transactionid' => $transaction->getCode(),
					]);
					
					logActivity('Pagseguro: Confirmação de pagamento para a fatura ' . $invoiceid . ', com o ID: ' . $response->getCode());
					echo'Pagseguro: Confirmação de pagamento para a fatura ' . $invoiceid . ', com o ID: ' . $response->getCode();
				}else{
					logActivity('Pagseguro: Estado do pagamento da fatura ' . $invoiceid . ', com o ID: ' . $response->getCode() . ', Status: ' . $transaction->getStatus());
					echo'Pagseguro: Estado do pagamento da fatura ' . $invoiceid . ', com o ID: ' . $response->getCode() . ', Status: ' . $transaction->getStatus();
				}
			}	
		}else{
			logActivity('Pagseguro: Falha ao receber callback para a fatura ' . $invoiceid . ', com o hash: ' . $hash . ', fatura não encontrada, notificação: ' . $notificationCode . '.');
			echo'Pagseguro: Falha ao receber callback para a fatura ' . $invoiceid . ', com o hash: ' . $hash . ', fatura não encontrada, notificação: ' . $notificationCode . '.';
		}
    }
}
