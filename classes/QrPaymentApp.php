<?php
/**
 * Clase ObjectModel para la gestión de Apps de Pago QR
 * Adaptado de CarrierCore para QrPayment con soporte de Posición
 */
class QrPaymentApp extends ObjectModel
{
    public $id_qrpayment;
    public $name;
    public $phone;
    public $email;
    public $max_amount;
    public $image_path;
    public $icon_path;
    public $active = true;
    
    /** @var int Posición para ordenar */
    public $position;

    /**
     * Definición del Modelo de Datos
     */
    public static $definition = [
        'table' => 'qrpayment',
        'primary' => 'id_qrpayment',
        'multilang' => false,
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 50],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255],
            'max_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'image_path' => ['type' => self::TYPE_STRING, 'size' => 255],
            'icon_path' => ['type' => self::TYPE_STRING, 'size' => 255],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            // Campo posición añadido
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
        ],
    ];

    /**
     * Añade la App calculando la posición automáticamente (al final de la lista)
     * Adaptado de Carrier::add()
     */
    public function add($autoDate = true, $nullValues = false)
    {
        if ($this->position <= 0) {
            $this->position = QrPaymentApp::getHigherPosition() + 1;
        }

        if (!parent::add($autoDate, $nullValues) || !Validate::isLoadedObject($this)) {
            return false;
        }

        return true;
    }

    /**
     * Elimina la App y reordena las posiciones restantes
     * Adaptado de Carrier::delete()
     */
    public function delete()
    {
        // Eliminación de imágenes físicas
        if ($this->image_path && file_exists(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->image_path)) {
            @unlink(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->image_path);
        }
        if ($this->icon_path && file_exists(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->icon_path)) {
            @unlink(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->icon_path);
        }

        if (!parent::delete()) {
            return false;
        }

        // Reordenar posiciones después de borrar
        QrPaymentApp::cleanPositions();

        return true;
    }

    /**
     * Actualiza la posición de una fila (para Drag & Drop)
     * Adaptado de Carrier::updatePosition()
     */
    public function updatePosition($way, $position)
    {
        if (!$res = Db::getInstance()->executeS(
            'SELECT `id_qrpayment`, `position`
            FROM `' . _DB_PREFIX_ . 'qrpayment`
            ORDER BY `position` ASC'
        )) {
            return false;
        }

        foreach ($res as $app) {
            if ((int) $app['id_qrpayment'] == (int) $this->id) {
                $moved_app = $app;
            }
        }

        if (!isset($moved_app) || !isset($position)) {
            return false;
        }

        // Actualizamos las posiciones intermedias para hacer hueco
        return Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'qrpayment`
            SET `position`= `position` ' . ($way ? '- 1' : '+ 1') . '
            WHERE `position`
            ' . ($way
                ? '> ' . (int) $moved_app['position'] . ' AND `position` <= ' . (int) $position
                : '< ' . (int) $moved_app['position'] . ' AND `position` >= ' . (int) $position
            ))
        && Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'qrpayment`
            SET `position` = ' . (int) $position . '
            WHERE `id_qrpayment` = ' . (int) $moved_app['id_qrpayment']);
    }

    /**
     * Reordena todas las posiciones desde 0
     * Adaptado de Carrier::cleanPositions()
     */
    public static function cleanPositions()
    {
        $return = true;

        $sql = 'SELECT `id_qrpayment`
                FROM `' . _DB_PREFIX_ . 'qrpayment`
                ORDER BY `position` ASC';
        $result = Db::getInstance()->executeS($sql);

        $i = 0;
        foreach ($result as $value) {
            $return = Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'qrpayment`
                SET `position` = ' . (int) $i++ . '
                WHERE `id_qrpayment` = ' . (int) $value['id_qrpayment']);
        }

        return $return;
    }

    /**
     * Obtiene la posición más alta actual
     * Adaptado de Carrier::getHigherPosition()
     */
    public static function getHigherPosition()
    {
        $sql = 'SELECT MAX(`position`)
                FROM `' . _DB_PREFIX_ . 'qrpayment`';
        $position = Db::getInstance()->getValue($sql);

        return (is_numeric($position)) ? $position : -1;
    }
}