<?php

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
    public $position;

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
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
        ],
    ];

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

    public function delete()
    {
        if ($this->image_path && strpos($this->image_path, '..') === false && file_exists(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->image_path)) {
            @unlink(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->image_path);
        }
        if ($this->icon_path && strpos($this->icon_path, '..') === false && file_exists(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->icon_path)) {
            @unlink(_PS_MODULE_DIR_ . 'qrpayment/img/' . $this->icon_path);
        }

        if (!parent::delete()) {
            return false;
        }

        QrPaymentApp::cleanPositions();
        return true;
    }

    public function updatePosition($way, $position)
    {
        $id_qrpayment = (int)$this->id;
        $position = (int)$position;

        if (!$res = Db::getInstance()->executeS(
            'SELECT `id_qrpayment`, `position`
            FROM `' . _DB_PREFIX_ . 'qrpayment`
            ORDER BY `position` ASC'
        )) {
            return false;
        }

        foreach ($res as $app) {
            if ((int) $app['id_qrpayment'] == $id_qrpayment) {
                $moved_app = $app;
            }
        }

        if (!isset($moved_app) || !isset($position)) {
            return false;
        }

        $moved_pos = (int)$moved_app['position'];

        return Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'qrpayment`
            SET `position`= `position` ' . ($way ? '- 1' : '+ 1') . '
            WHERE `position`
            ' . ($way
                ? '> ' . $moved_pos . ' AND `position` <= ' . $position
                : '< ' . $moved_pos . ' AND `position` >= ' . $position
            ))
        && Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'qrpayment`
            SET `position` = ' . $position . '
            WHERE `id_qrpayment` = ' . $moved_app['id_qrpayment']);
    }

    public static function cleanPositions()
    {
        $return = true;
        $sql = 'SELECT `id_qrpayment` FROM `' . _DB_PREFIX_ . 'qrpayment` ORDER BY `position` ASC';
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

    public static function getHigherPosition()
    {
        $sql = 'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'qrpayment`';
        $position = Db::getInstance()->getValue($sql);
        return (is_numeric($position)) ? (int)$position : -1;
    }
}