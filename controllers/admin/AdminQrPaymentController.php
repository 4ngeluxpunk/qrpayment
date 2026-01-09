<?php

require_once _PS_MODULE_DIR_ . 'qrpayment/classes/QrPaymentApp.php';

class AdminQrPaymentController extends ModuleAdminController
{
    protected $position_identifier = 'id_qrpayment';

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'qrpayment';
        $this->className = 'QrPaymentApp';
        $this->identifier = 'id_qrpayment';
        $this->lang = false;
        
        $this->_defaultOrderBy = 'position';

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', [], 'Admin.Notifications.Info'),
                'confirm' => $this->trans('Delete selected items?', [], 'Admin.Notifications.Info'),
                'icon' => 'icon-trash',
            ],
        ];

        $this->fields_list = [
            'id_qrpayment' => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'name' => [
                'title' => $this->module->l('Nombre App'),
            ],
            'phone' => [
                'title' => $this->module->l('Teléfono / Cuenta'),
            ],
            'max_amount' => [
                'title' => $this->module->l('Monto Máximo'),
                'type' => 'price',
                'currency' => true,
            ],
            'active' => [
                'title' => $this->trans('Status', [], 'Admin.Global'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'class' => 'fixed-width-sm',
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->trans('Position', [], 'Admin.Global'),
                'filter_key' => 'a!position',
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'position' => 'position',
            ],
        ];
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = $this->module->l('Métodos de Pago QR');
        
        if ($this->display != 'add' && $this->display != 'edit') {
            $this->page_header_toolbar_btn['new_qrapp'] = [
                'href' => self::$currentIndex . '&addqrpayment&token=' . $this->token,
                'desc' => $this->module->l('Añadir nueva App'),
                'icon' => 'process-icon-new',
            ];
        }
        parent::initPageHeaderToolbar();
    }

    public function renderForm()
    {
        $obj = $this->loadObject(true);
        $base_url = $this->module->getPathUri() . 'img/';
        $icon_url = ($obj && $obj->icon_path) ? $base_url . $obj->icon_path : false;
        $qr_url = ($obj && $obj->image_path) ? $base_url . $obj->image_path : false;

        $this->fields_form = [
            'legend' => [
                'title' => $this->module->l('Información de la App QR'),
                'icon' => 'icon-money',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->module->l('Nombre de la App'),
                    'name' => 'name',
                    'required' => true,
                    'hint' => $this->module->l('Ej: Yape, Plin, PayPal'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->l('Teléfono o Número de Cuenta'),
                    'name' => 'phone',
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->l('Email (Opcional)'),
                    'name' => 'email',
                ],
                [
                    'type' => 'file',
                    'label' => $this->module->l('Icono Pequeño'),
                    'name' => 'icon_path',
                    'display_image' => true,
                    'image' => $icon_url ? '<img src="'.$icon_url.'" style="max-width: 80px;"/>' : false,
                ],
                [
                    'type' => 'file',
                    'label' => $this->module->l('Código QR (Imagen)'),
                    'name' => 'image_path',
                    'display_image' => true,
                    'image' => $qr_url ? '<img src="'.$qr_url.'" style="max-width: 150px;"/>' : false,
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->l('Monto Máximo'),
                    'name' => 'max_amount',
                    'prefix' => $this->context->currency->sign,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Status', [], 'Admin.Global'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
            ],
        ];

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitAddqrpayment')) {
            $this->handleImageUpload('icon_path', 'icon_');
            $this->handleImageUpload('image_path', 'qr_');
        }
        return parent::postProcess();
    }

    protected function handleImageUpload($inputName, $prefix)
    {
        if (isset($_FILES[$inputName]) && isset($_FILES[$inputName]['tmp_name']) && !empty($_FILES[$inputName]['tmp_name'])) {
            $ext = substr($_FILES[$inputName]['name'], strrpos($_FILES[$inputName]['name'], '.') + 1);
            $file_name = $prefix . md5(uniqid(rand(), true)) . '.' . $ext;
            $path = _PS_MODULE_DIR_ . 'qrpayment/img/' . $file_name;
            if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $path)) {
                $_POST[$inputName] = $file_name;
            }
        }
    }

    public function ajaxProcessUpdatePositions()
    {
        $way = (bool) (Tools::getValue('way'));
        $id_qrpayment = (int) (Tools::getValue('id'));
        $positions = Tools::getValue($this->table);

        foreach ($positions as $position => $value) {
            $pos = explode('_', $value);

            if (isset($pos[2]) && (int) $pos[2] === $id_qrpayment) {
                $app = new QrPaymentApp((int) $pos[2]);
                if (Validate::isLoadedObject($app)) {
                    if (isset($position) && $app->updatePosition($way, $position)) {
                        echo 'ok position ' . (int) $position . ' for app ' . (int) $pos[1] . '\r\n';
                    } else {
                        echo '{"hasError" : true, "errors" : "Can not update app ' . (int) $id_qrpayment . ' to position ' . (int) $position . ' "}';
                    }
                } else {
                    echo '{"hasError" : true, "errors" : "This app (' . (int) $id_qrpayment . ') can t be loaded"}';
                }
                break;
            }
        }
    }
}