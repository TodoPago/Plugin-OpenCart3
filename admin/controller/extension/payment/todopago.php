<?php
require_once DIR_APPLICATION . '../catalog/controller/extension/todopago/vendor/autoload.php';
require_once DIR_SYSTEM . 'library/todopago/todopago_ctes.php';
require_once DIR_SYSTEM . 'library/todopago/Logger/loggerFactory.php';

class ControllerExtensionPaymentTodopago extends Controller
{
    const INSTALL = 'install';
    const UPGRADE = 'upgrade';

    private $error = array();

    public function index()
    {
        $this->document->setTitle('TodoPago Configuration');
        $this->document->addScript('view/javascript/todopago/jquery.dataTables.min.js');
        $this->document->addScript('view/javascript/todopago/tinglemodal.js');
        $this->document->addScript('view/javascript/todopago/todopago_functions.js');
        $this->document->addStyle('view/stylesheet/todopago/jquery.dataTables.css');
        $this->document->addStyle('view/stylesheet/todopago.css');
        $this->document->addStyle('view/stylesheet/todopago/tinglemodal.css');
        $this->load->language('extension/payment/todopago');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/todopago');
        $this->load->model('extension/todopago/transaccion_admin');
        $this->load->model('extension/todopago/addressbook_admin');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { //Si Viene con datos via post viene con datos del menú de configuracion.
            $this->model_setting_setting->editSetting('payment_todopago', $this->request->post);
            if ($this->request->post['upgrade'] == '1') { //Si necesita upgradear llamamos al _install()
                $this->response->redirect($this->url->link('extension/payment/todopago/_install', 'action=' . self::UPGRADE . '&user_token=' . $this->session->data['user_token'] . '&pluginVersion=' . $this->model_extension_payment__todopago->getVersion(), true));
            } else {
                $this->session->data['success'] = "Guardado.";
            }
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        } else {
            if ($this->request->server['REQUEST_METHOD'] == 'POST') {
                $this->session->data['error'] = "Error en el rango del campo timeout";
            }
        }

        $data['heading_title'] = "Todo Pago";

        //Upgrade verification
        $installedVersion = $this->model_extension_payment_todopago->getVersion();

        $this->logger->debug("version instalada: " . $installedVersion);
        $this->logger->debug("Versión a instalar: " . TP_VERSION);
        $data['installed_todopago_version'] = $installedVersion;
        $data['need_upgrade'] = (TP_VERSION > $installedVersion) ? true : false;
        $data['payment_todopago_version'] = $installedVersion;
        $data['entry_text_config_two'] = $this->language->get('text_config_two');

        //Botón de Guardar / Upgrade
        if ($data['need_upgrade']) {
            if (version_compare(TP_VERSION, '1.0.0') >= 0) {
                $this->logger->debug($this->model_extension_payment_todopago->getVersion());
                $this->do_install($this->model_extension_payment_todopago->getVersion());
                $settings['payment_todopago_version'] = $installedVersion;
                $this->model_setting_setting->editSetting('payment_todopago', $settings);
                $data['button_save'] = $this->language->get('text_button_save');
                $data['button_save_class'] = "fa-save";
                $data['need_upgrade'] = false;
            } else {
                $data['button_save'] = "Upgrade";
                $data['button_save_class'] = "fa-arrow-circle-o-up";
            }

        } else {
            $data['button_save'] = $this->language->get('text_button_save');
            $data['button_save_class'] = "fa-save";
        }

        $data['button_cancel'] = $this->language->get('text_button_cancel');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');

        //breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );
        //changelog quitado text_payment
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/todopago', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('extension/payment/todopago', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        //warning
        if (isset($this->error)) { //I ignore if it is the correct key
            $data['warning'] = $this->error;
        } else {
            $data['warning'] = "";
        }

        //error
        if (isset($this->session->data['error'])) { //It must work...
            $data['error']['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        }

        //datos para el tag general
        if (isset($this->request->post['payment_todopago_status'])) {
            $data['payment_todopago_status'] = $this->request->post['payment_todopago_status'];
        } else {
            $data['payment_todopago_status'] = $this->config->get('payment_todopago_status');
        }

        if (isset($this->request->post['payment_todopago_segmentodelcomercio'])) {
            $data['payment_todopago_segmentodelcomercio'] = $this->request->post['payment_todopago_segmentodelcomercio'];
        } else {
            $data['payment_todopago_segmentodelcomercio'] = $this->config->get('payment_todopago_segmentodelcomercio');
        }

        if (isset($this->request->post['payment_todopago_canaldeingresodelpedido'])) {
            $data['payment_todopago_canaldeingresodelpedido'] = $this->request->post['payment_todopago_canaldeingresodelpedido'];
        } else {
            $data['payment_todopago_canaldeingresodelpedido'] = $this->config->get('payment_todopago_canaldeingresodelpedido');
        }

        if (isset($this->request->post['payment_todopago_deadline'])) {
            $data['payment_todopago_deadline'] = $this->request->post['payment_todopago_deadline'];
        } else {
            $data['payment_todopago_deadline'] = $this->config->get('payment_todopago_deadline');
        }

        if (isset($this->request->post['payment_todopago_modotestproduccion'])) {
            $data['payment_todopago_modotestproduccion'] = $this->request->post['payment_todopago_modotestproduccion'];
        } else {
            $data['payment_todopago_modotestproduccion'] = $this->config->get('payment_todopago_modotestproduccion');
        }

        if (isset($this->request->post['payment_todopago_formulario'])) {
            $data['payment_todopago_formulario'] = $this->request->post['payment_todopago_formulario'];
        } else {
            $data['payment_todopago_formulario'] = $this->config->get('payment_todopago_formulario');
        }

        if (isset($this->request->post['payment_todopago_maxinstallments'])) {
            $data['payment_todopago_maxinstallments'] = $this->request->post['payment_todopago_maxinstallments'];
        } else {
            $data['payment_todopago_maxinstallments'] = $this->config->get('payment_todopago_maxinstallments');
        }

        if (isset($this->request->post['payment_todopago_timeout_form_enabled'])) {
            $data['payment_todopago_timeout_form_enabled'] = 1;
        } else {
            $data['payment_todopago_timeout_form_enabled'] = $this->config->get('payment_todopago_timeout_form_enabled');
        }

        if (isset($this->request->post['payment_todopago_timeout_form'])) {
            $data['payment_todopago_timeout_form'] = $this->request->post['payment_todopago_timeout_form'];
        } else {
            $data['payment_todopago_timeout_form'] = $this->config->get('payment_todopago_timeout_form');
        }

        if (isset($this->request->post['payment_todopago_cart'])) {
            $data['payment_todopago_cart'] = $this->request->post['payment_todopago_cart'];
        } else {
            $data['payment_todopago_cart'] = $this->config->get('payment_todopago_cart');
        }

        // validar a través de gmaps
        if (isset($this->request->post['payment_todopago_gmaps_validacion'])) {
            $data['payment_todopago_gmaps_validacion'] = $this->request->post['payment_todopago_gmaps_validacion'];
        } else {
            $data['payment_todopago_gmaps_validacion'] = $this->config->get('payment_todopago_gmaps_validacion');
        }

        //datos para tags ambiente test
        if (isset($this->request->post['payment_todopago_authorizationHTTPtest'])) {
            $data['payment_todopago_authorizationHTTPtest'] = $this->request->post['payment_todopago_authorizationHTTPtest'];
        } else {
            $data['payment_todopago_authorizationHTTPtest'] = $this->config->get('payment_todopago_authorizationHTTPtest');
        }

        if (isset($this->request->post['payment_todopago_idsitetest'])) {
            $data['payment_todopago_idsitetest'] = $this->request->post['payment_todopago_idsitetest'];
        } else {
            $data['payment_todopago_idsitetest'] = $this->config->get('payment_todopago_idsitetest');
        }

        if (isset($this->request->post['payment_todopago_securitytest'])) {
            $data['payment_todopago_securitytest'] = $this->request->post['payment_todopago_securitytest'];
        } else {
            $data['payment_todopago_securitytest'] = $this->config->get('payment_todopago_securitytest');
        }

        //datos para tags ambiente produccion
        if (isset($this->request->post['payment_todopago_authorizationHTTPproduccion'])) {
            $data['payment_todopago_authorizationHTTPproduccion'] = $this->request->post['payment_todopago_authorizationHTTPproduccion'];
        } else {
            $data['payment_todopago_authorizationHTTPproduccion'] = $this->config->get('payment_todopago_authorizationHTTPproduccion');
        }

        if (isset($this->request->post['payment_todopago_idsiteproduccion'])) {
            $data['payment_todopago_idsiteproduccion'] = $this->request->post['payment_todopago_idsiteproduccion'];
        } else {
            $data['payment_todopago_idsiteproduccion'] = $this->config->get('payment_todopago_idsiteproduccion');
        }

        if (isset($this->request->post['payment_todopago_securityproduccion'])) {
            $data['payment_todopago_securityproduccion'] = $this->request->post['payment_todopago_securityproduccion'];
        } else {
            $data['payment_todopago_securityproduccion'] = $this->config->get('payment_todopago_securityproduccion');
        }

        //datos para estado del pedido
        if (isset($this->request->post['payment_todopago_order_status_id_aprov'])) {
            $data['payment_todopago_order_status_id_aprov'] = $this->request->post['payment_todopago_order_status_id_aprov'];
        } else {
            $data['payment_todopago_order_status_id_aprov'] = $this->config->get('payment_todopago_order_status_id_aprov');
        }

        if (isset($this->request->post['payment_todopago_order_status_id_rech'])) {
            $data['payment_todopago_order_status_id_rech'] = $this->request->post['payment_todopago_order_status_id_rech'];
        } else {
            $data['payment_todopago_order_status_id_rech'] = $this->config->get('payment_todopago_order_status_id_rech');
        }

        if (isset($this->request->post['payment_todopago_order_status_id_off'])) {
            $data['payment_todopago_order_status_id_off'] = $this->request->post['payment_todopago_order_status_id_off'];
        } else {
            $data['payment_todopago_order_status_id_off'] = $this->config->get('payment_todopago_order_status_id_off');
        }

        if (isset($this->request->post['payment_todopago_order_status_id_pro'])) {
            $data['payment_todopago_order_status_id_pro'] = $this->request->post['payment_todopago_order_status_id_pro'];
        } else {
            $data['payment_todopago_order_status_id_pro'] = $this->config->get('payment_todopago_order_status_id_pro');
        }
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['extension'] = 'extension';
        $this->template = 'extension/payment/todopago';

        $data['header'] = $this->load->controller("common/header");
        //column left is loaded via controller and should be placed in all modules
        $data['column_left'] = $this->load->controller("common/column_left");
        $data['footer'] = $this->load->controller("common/footer");
        $data['user_token'] = $this->session->data['user_token'];

        //getOrders()
        $this->load->model('extension/payment/todopago');
        $orders_array = $this->model_extension_payment_todopago->get_orders();
        $data['orders_array'] = json_encode($orders_array->rows);

        $data['url_get_status'] = $this->url->link("extension/payment/todopago/get_status&user_token=" . $this->session->data["user_token"]);
        $data['url_devolver'] = $this->url->link("extension/payment/todopago/devolver&user_token=" . $this->session->data["user_token"]);

        $this->response->setOutput($this->load->view($this->template, $data));
    }

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->logger = loggerFactory::createLogger();
        // cargo urls del json segun sea la version
        //$data = file_get_contents(DIR_SYSTEM . "config/todopago_routes.json");
        //$payment_todopago_routes = json_decode($data, true);
        //$arr_version = explode('.', TP_VERSION);
        //$oc_version = $arr_version[0] . '.' . $arr_version[1];
        //$this->payment_todopago_routes = $payment_todopago_routes[$oc_version];
    }

    public function install()
    {
        $this->_install('install');
        //Decomentar cuando se reactive la instalación custom $this->response->redirect($this->url->link($this->payment_todopago_routes['payment-extension'] . '/confirm_installation', 'user_token=' . $this->session->data['user_token'], true)); //Redirecciono para poder salir del ciclo de instalación y poder mostrar mi pantalla.
    }


    public function confirm_installation()
    {
        //Preparo twig
        /*$data['header'] = $this->load->controller("common/header");
        $data['column_left'] = $this->load->controller("common/column_left");
        $data['footer'] = $this->load->controller("common/footer");
        $data['todopago_version'] = payment_todopago_VERSION;
        $data['install_button_text'] = 'Instalar';
        $data['cancel_button_text'] = 'Cancelar';
        $data['install_button_action'] = html_entity_decode($this->url->link($this->payment_todopago_routes['payment-module'] . '/_install', 'action=' . self::INSTALL . '&user_token=' . $this->session->data['user_token'], true));
        $data['cancel_button_action'] = html_entity_decode($this->url->link($this->payment_todopago_routes['payment-extension'] . '/_revert_installation', 'user_token=' . $this->session->data['user_token'], true)); //Al llegar la pantalla ell plugin ya se instaló en el commerce por lo qe hace falta dsinstalarlo
        $data['back_button_message'] = "Esto interrumpirá la instalación";
        $data['visible_url'] = html_entity_decode($this->url->link($this->payment_todopago_routes['payment-module'] . '/install', 'user_token=' . $this->session->data['user_token'], true)); //Al llegar la pantalla ell plugin ya se instaló en el commerce por lo qe hace falta dsinstalarlo
        $data['extension'] = $this->payment_todopago_routes['extension'];
        $this->template = $this->payment_todopago_routes['template'] . '/install';

        $this->response->setOutput($this->load->view($this->template, $data));
        */
    }

    private function do_install($plugin_version)
    {
        /*******************************************************************
         *Al no tener breaks entrará en todos los case posteriores.         *
         *TODAS LAS VERSIONES DEBEN APARECER,                               *
         *de lo contrario LA VERSION QUE NO APAREZCA NO PODRÁ UPGRADEARSE   *
         *******************************************************************/
        switch ($plugin_version) {
            case '1.0.0':
                $this->logger->debug("Upgrade to v1.0.0");
                $statusCode = $this->createTables();
                $this->load->model('setting/setting');
                $settings['payment_todopago_version'] = TP_VERSION;
                $this->model_setting_setting->editSetting('payment_todopago', $settings); //Registra en la tabla el nro de Versión a la que se ha actualizado
        }
        if (isset($statusCode) && $statusCode !== 200) {
            return 'Error;';
        } else
            return 200;
    }

    private function createTables()
    {
        $queries = array();
        $errores = array();
        array_push($queries, $this->model_extension_todopago_transaccion_admin->createTable()); //crea la tabla todopago_transaccion
        array_push($queries, $this->model_extension_payment_todopago->setProvincesCode()); //Guarda los códigos de prevención de fraude para las provincias
        array_push($queries, $this->model_extension_payment_todopago->setPostCodeRequired()); //Setea el código postal obligatorio para Argentina
        array_push($queries, $this->model_extension_todopago_addressbook_admin->createTable()); //Crea la tabla direcciones
        foreach ($queries as $query) {
            if ($query !== 200) {
                $this->logger->error($query);
                array_push($errores, $query);
            }
        }
        if (empty($errores))
            return 200;
        else
            return 'Error';
    }

    public function _install($metodo)
    {
        //Este es el método que se ocupa en realidad de la instalación así como del upgrade
        //if (isset($this->request->get['action'])) {

        //$action = $this->request->get['action']; //Acción a realizar (puede ser self::INSTALL o self::UPGRADE)

        //Modelos necesarios
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/todopago');
        $this->load->model('extension/todopago/transaccion_admin');
        $this->load->model('extension/todopago/addressbook_admin');
        $this->logger->info("Verifying required upgrades");
        $actualVersion = TP_VERSION;
        $status = $this->do_install($actualVersion);

        if ($status !== 200) {
            //if ($action == self::UPGRADE) {
            if ($metodo == 'upgrade') {
                $this->session->data['success'] = 'Upgrade finalizado.';
            } else {
                try {
                    $this->load->model('setting/setting');
                    $settings['payment_todopago_version'] = $actualVersion;
                    $this->model_setting_setting->editSetting('payment_todopago', $settings); //Registra en la tabla el nro de Versión a la que se ha actualizado
                    $this->session->data['success'] = 'Instalación finalizada.';
                } catch (Exception $e) {
                    $errorMessage = 'Fallo deconocido, se pedirá reintentar';
                    $this->logger->fatal($errorMessage, $e);
                }
            }
        } else {
            $this->session->data['success'] = 'Upgraded.';
        }

        //$this->response->redirect('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true);
        $this->logger->info('Todopago instalado correctamente!');
        /*} else {
            $this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)); //Nunca deberíamos haber llegado aquí, así que nos vamos
        }*/ //else horrible obsoleto si no hay instalación custom
    }

    public function _revert_installation()
    { //Desinstalación silenciosa del plugin para el commerce (para cuando no se finaliza la instalación)
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('payment', 'todopago');
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true));
    }

    /*public function uninstall() desinstalador custom
    {
        $this->response->redirect($this->url->link($this->payment_todopago_routes['payment-extension'] . '/config_uninstallation', 'user_token=' . $this->session->data['user_token'], true)); //Recirijo para salir del ciclo de desinstallación del plugin y pooder mostrar mi pantalla
    }*/

    public
    function config_uninstallation()
    { //Permite seleccionar qué cambios de instalación deshacer

        //Se prepara el twig
        $this->document->setTitle('Desinstalación TodoPago');
        $this->document->addStyle('view/stylesheet/todopago/back.css');

        $data['header'] = $this->load->controller("common/header");
        $data['column_left'] = $this->load->controller("common/column_left");
        $data['footer'] = $this->load->controller("common/footer");
        $data['user_token'] = $this->session->data['user_token'];
        //$data['todopago_version'] = ;
        $data['button_continue_text'] = 'Continue';
        $data['button_continue_action'] = $this->url->link('extension/payment/todopago/_uninstall', 'user_token=' . $this->session->data['user_token'], true);
        $data['back_button_message'] = "Esto ejecutará la instalación básica (No se ejecutará ninguna de las acciones descriptas en la página actual)";
        $data['extension'] = 'extension';
        $this->template = 'extension/todopago/uninstall';
        $this->response->setOutput($this->load->view($this->template, $data));
    }

    public function uninstall() //Método de desinstalación interno, deshace los cambios seleccionados.
    {
        //$this->load->model($this->payment_todopago_routes['payment-module']);
        //$this->load->model($this->payment_todopago_routes['template'].'/transaccion_admin');
        $this->load->model('extension/payment/todopago');
        $this->load->model('extension/todopago/transaccion_admin');
        $this->load->model('extension/todopago/addressbook_admin');

        //if (isset($this->request->post['revert_postcode_required']))
        //if (isset($this->request->post['drop_column_cs_code']))
        //if (isset($this->request->post['drop_table_todopago_transaccion']))
        //if (isset($this->request->post['drop_table_todopago_addressbook']))
        //$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true));
        $error = array();
        $queries = array();
        array_push($queries, $this->model_extension_payment_todopago->setPostCodeRequired(false));
        array_push($queries, $this->model_extension_payment_todopago->unsetProvincesCode());
        array_push($queries, $this->model_extension_todopago_transaccion_admin->dropTable());
        array_push($queries, $this->model_extension_todopago_addressbook_admin->dropTable());
        foreach ($queries as $query) {
            if ($query !== 200) {
                array_push($error, $query);
                $this->logger->error("Falló la desinstalación. Error: " . $query);
            }
        }
        if (empty($error)) {
            $this->logger->info('¡TodoPago desinstalado!');
        }
    }

    protected function validate()
    {
        $timeout_form = '';
        $res = true;

        if (isset($this->request->post['payment_todopago_timeout_form_enabled'])) {
            if (isset($this->request->post['payment_todopago_timeout_form'])) {
                $timeout_form = $this->request->post['payment_todopago_timeout_form'];
            } else {
                $timeout_form = $this->config->get('payment_todopago_timeout_form');
            }

            if ($timeout_form < 60 * 5 * 1000 || $timeout_form > 6 * 60 * 60 * 1000) {
                $res = false;
            }
        }

        return $res;
    }

// LEGACY
    /*public function render()
    {
        return $this->load->view($this->template, $data);
    }
    */
    public function getOrders()
    {
        $this->load->model('extension/payment/todopago');
        $orders_array = $this->model_extension_payment_todopago->get_orders();
        $orders_array = json_encode($orders_array->rows);
    }


    public function devolver()
    {
        $monto = $_POST["monto"];
        $order_id = $_POST['order_id'];
        $transaction_row = $this->db->query("SELECT request_key FROM `" . DB_PREFIX . "todopago_transaccion` WHERE id_orden=$order_id");
        $mode = $this->get_mode();
        $authorizationHTTP = $this->get_authorizationHTTP();
        $request_key = $transaction_row->row["request_key"];

        if (empty($request_key)) {
            echo "No es posible hacer devolución sobre esa transacción";
        } else {
            try {
                $connector = new TodoPago\Sdk($authorizationHTTP, $mode);
                $options = array(
                    "Security" => $this->get_security_code(), // API Key del comercio asignada por TodoPago
                    "Merchant" => $this->get_id_site(), // Merchant o Nro de comercio asignado por TodoPago
                    "RequestKey" => $request_key//, // RequestKey devuelto como respuesta del servicio SendAutorizeRequest
                    //"AMOUNT" => $monto // Opcional. Monto a devolver, si no se envía, se trata de una devolución total
                );

                if (empty($monto)) {
                    $this->logger->info("Pedido de devolución total pesos de la orden $order_id");
                    $this->logger->info(json_encode($options));
                    $resp = $connector->voidRequest($options);
                    $this->logger->info(json_encode($resp));
                } else {
                    $this->logger->info("Pedido de devolución por $monto pesos de la orden $order_id");
                    $options["AMOUNT"] = $monto;
                    $this->logger->info(json_encode($options));
                    $resp = $connector->returnRequest($options);
                    $this->logger->info(json_encode($resp));
                }

                if ($resp["StatusCode"] == "2011") {
                    $this->load->model("sale/return");
                    if (empty($monto)) {
                        $order_row = $this->db->query("SELECT total FROM `" . DB_PREFIX . "order` WHERE order_id = $order_id AND payment_code='todopago';");
                        $options["AMOUNT"] = $order_row->row["total"];
                    }

                    $this->model_sale_return->addReturn($this->getReturnValues($order_id, $resp, $options["AMOUNT"]));

                    echo("La devolución ha sido efectuada con éxito");


                } else {
                    if ($resp["StatusMessage"]) {
                        $complete_value = json_encode($resp["StatusMessage"]);
                        $complete_value = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                            return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                        }, $complete_value);
                        echo $complete_value;
                    } else {
                        echo "No se pudo realizar la devolución.";
                    }
                }

            } catch (Exception $e) {
                echo json_encode($e->getMessage());
            }
        }
    }

    public function get_status()
    {
        $order_id = $_GET['order_id'];
        $this->load->model('extension/todopago/transaccion');
        $transaction = $this->model_extension_todopago_transaccion;
        $this->logger->debug('todopago -  step: ' . $transaction->getStep($order_id));
        // if($transaction->getStep($order_id) == $transaction->getTransactionFinished()){
        $authorizationHTTP = $this->get_authorizationHTTP();
        $this->logger->debug('Authorization HTTP: ' . json_encode($authorizationHTTP));
        $mode = $this->get_mode();
        $this->logger->debug('Mode: ' . $mode);
        try {
            $connector = new TodoPago\Sdk($authorizationHTTP, $mode);
            $optionsGS = array('MERCHANT' => $this->get_id_site(), 'OPERATIONID' => $order_id);
            $this->logger->debug('Options GetStatus: ' . json_encode($optionsGS));
            $status = $connector->getStatus($optionsGS);
            $status_json = json_encode($status);
            $this->logger->info("GETSTATUS: " . $status_json);
            $rta = '';

            $refunds = $status['Operations']['REFUNDS'];
            $auxArray = array(
                "REFUND" => $refunds
            );

            $aux = 'REFUND';
            $auxColection = 'REFUNDS';

            if ($status) {
                if (isset($status['Operations']) && is_array($status['Operations'])) {
                    foreach ($status['Operations'] as $key => $value) {
                        if (is_array($value) && $key == $auxColection) {
                            $rta .= "$key: <br/>";
                            foreach ($auxArray[$aux] as $key2 => $value2) {
                                $rta .= $aux . " <br/>";
                                if (is_array($value2)) {
                                    foreach ($value2 as $key3 => $value3) {
                                        if (is_array($value3)) {
                                            foreach ($value3 as $key4 => $value4) {
                                                $complete_value = json_encode($value4);
                                                $complete_value = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                                                    return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                                                }, $complete_value);
                                                $rta .= "   - $key4: $complete_value <br/>";
                                            }
                                        } else {
                                            $complete_value = json_encode($value3);
                                            $complete_value = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                                                return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                                            }, $complete_value);
                                            $rta .= "   - $key3: $complete_value <br/>";
                                        }
                                    }
                                } else {
                                    $complete_value = json_encode($value2);
                                    $complete_value = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                                        return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                                    }, $complete_value);
                                    $rta .= "   - $key2: $complete_value <br/>";
                                }
                            }
                        } else {
                            if (is_array($value)) {
                                $rta .= "$key: <br/>";
                            } else {
                                $complete_value = json_encode($value);
                                $complete_value = preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                                    return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                                }, $complete_value);
                                $rta .= "$key: $complete_value <br/>";
                            }
                        }
                    }
                } else {
                    $rta = 'No hay operaciones para esta orden.';
                }
            } else {
                $rta = 'No se ecuentra la operación. Esto puede deberse a que la operación no se haya finalizado o a una configuración erronea.';
            }
        } catch (Exception $e) {
            $this->logger->fatal("Ha surgido un error al consultar el estado de la orden: ", $e);
            $rta = 'ERROR AL CONSULTAR LA ORDEN';
        }
//        }
//        else{
//            $rta = "NO HAY INFORMACIÓN DE PAGO";
//        }
        echo($rta);

    }


    private function get_authorizationHTTP()
    {
        if ($this->get_mode() == "test") {
            $htpayment_todopago_header = html_entity_decode($this->config->get('payment_todopago_authorizationHTTPtest'));

        } else {
            $htpayment_todopago_header = html_entity_decode($this->config->get('payment_todopago_authorizationHTTPproduccion'));
        }

        if (json_decode($htpayment_todopago_header, TRUE) == null) {
            $htpayment_todopago_header = array("Authorization" => $htpayment_todopago_header);
        } else {
            $htpayment_todopago_header = json_decode($htpayment_todopago_header, TRUE);
        }

        return $htpayment_todopago_header;
    }

    private function get_mode()
    {
        return html_entity_decode($this->config->get('payment_todopago_modotestproduccion'));
    }

    private
    function get_id_site()
    {
        if ($this->get_mode() == "test") {
            return html_entity_decode($this->config->get('payment_todopago_idsitetest'));
        } else {
            return html_entity_decode($this->config->get('payment_todopago_idsiteproduccion'));
        }
    }

    private function get_security_code()
    {
        if ($this->get_mode() == "test") {
            return html_entity_decode($this->config->get('payment_todopago_securitytest'));
        } else {
            return html_entity_decode($this->config->get('payment_todopago_securityproduccion'));
        }
    }

    private function getReturnValues($order_id, $resp, $amout)
    {
        $this->load->model("sale/order");
        $order = $this->model_sale_order->getOrder($order_id);
        $returnValues = array(
            "order_id" => $order_id,
            "firstname" => $order["firstname"],
            "lastname" => $order["lastname"],
            "telephone" => $order["telephone"],
            "email" => $order["email"],
            "product" => "DEVOLUCION TODOPAGO",
            "model" => "$" . $amout,
            "comment" => json_encode($resp),
            "customer_id" => $order["customer_id"],
            "quantity" => "1",
            "date_ordered" => $order["date_added"],
            "product_id" => 0,
            "return_reason_id" => 0,
            "return_action_id" => 0,
            "return_status_id" => 0,
            "opened" => 0

        );

        return $returnValues;

    }


//Descomentar e implementar cuando se habiliten los verticales que requieren campos adicionales:
    /*private function createAttributeGroup($name){
        $data = array('sort_order' => 0);

        $this->load->model('catalog/attribute_group');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages(); //obitene todos los idiomas instalados
            $attributeGroupDescription = array();
        foreach ($languages as $lang){
            $attributeGroupDescription[$lang['language_id']] = array('name' => $name); //setea el nombre en ese idioma
        }
        $data['attribute_group_description'] = $attributeGroupDescription;

        $this->model_catalog_attribute_group->addAttributeGroup($data); //Crea el attribute_group

        return $this->getAttributeGroupId($name); //devuelve el id del nuevo grupo
    }

    private function createAttribute($name, $attributeGroupId){
        $data = array(
            'sort_order' => 0,
            'attribute_group_id' => $attributeGroupId
        );

        $this->load->model('catalog/attribute');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();
        $attributeDescription = array();
        foreach ($languages as $lang){
                $attributeDescription[$lang['language_id']] = array('name' => $name);
        }
        $data['attribute_description'] = $attributeDescription;

        $this->model_catalog_attribute->addAttribute($data);

    }

    private function getAttributeGroupId($attributeGroupName){

        $this->load->model('catalog/attribute_group');
        $attributeGroups = $this->model_catalog_attribute_group->getAttributeGroups();
        foreach ($attributeGroups as $attrGrp){
            if ($attrGrp['name'] == $attributeGroupName) {
                $attributeGroupId = $attrGrp['attribute_group_id'];
                break;
            }
        }
        return $attributeGroupId;
    }

    private function getAttributeId($attributeName){
        $this->load->model('catalog/attribute');
        $attributes = $this->model_catalog_attribute->getAttributes();
        foreach ($attributes as $attr){
            if ($attr['name'] == $attributeName) {
                $attributeId = $attr['attribute_id'];
                break;
            }
        }
        return $attributeId;
    }

    private function deleteControlFraudeAttributeGroup(){
        $controlFraudeAttributeGroupId = $this->getAttributeGroupId(payment_todopago_CS_ATTGROUP);
        $this->load->model('catalog/attribute');
        $controlFraudeAttributeGroupAttributes = $this->model_catalog_attribute->getAttributesByAttributeGroupId(array('filter_attribute_group_id' => $controlFraudeAttributeGroupId));
        foreach ($controlFraudeAttributeGroupAttributes as $attribute){
            $this->model_catalog_attribute->deleteAttribute($attribute['attribute_id']);
        }
        $attributeGroups = $this->model_catalog_attribute_group->getAttributeGroups();
        $this->model_catalog_attribute_group->deleteAttributeGroup($controlFraudeGroupId);
    }*/
}