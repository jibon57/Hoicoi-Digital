<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');
/**

 * @author Jibon Lawrence Costa
 * @Email: jiboncosta57@gmail.com
 * @copyirght Copyright (C) Hoicoi Extension Portal
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 *
 * http://hoicoimasti.com
 */
if (!class_exists('vmCustomPlugin'))
    include_once JPATH_VM_PLUGINS . DS . 'vmcustomplugin.php';

include_once VMPATH_ADMIN . DS . 'models' . DS . 'orders.php';
include_once VMPATH_ADMIN . DS . 'models' . DS . 'customfields.php';

class plgVmCustomHoicoi_digital extends vmCustomPlugin {

    function __construct(& $subject, $config) {

        parent::__construct($subject, $config);

        $varsToPush = array(
            'media_id' => array('', 'int'),
            'calender' => array('', 'string'),
            'duration' => array('', 'string')
        );

        $this->setConfigParameterable('customfield_params', $varsToPush);
    }

    // get product param for this plugin on edit
    function plgVmOnProductEdit($field, $product_id, &$row, &$retValue) {

        if ($field->custom_element != $this->_name)
            return '';

        $medias = $this->getMedia();

        foreach ($medias as $media) {
            $val = $media['virtuemart_media_id'];
            $text = $media['file_title'];
            $options[] = JHtml::_('select.option', $val, $text);
        }

        $media_files = JHTML::_('select.genericlist', $options, 'customfield_params[' . $row . '][media_id][]', 'size="10" multiple="multiple"', 'value', 'text', $field->media_id);

        $options = array();
        $options [] = JHtml::_('select.option', 'days', 'day(s)');
        $options [] = JHtml::_('select.option', 'months', 'month(s)');
        $options [] = JHtml::_('select.option', 'years', 'year(s)');
        $options [] = JHtml::_('select.option', 'lifetime', 'Lifetime');
        $calender = JHTML::_('select.genericlist', $options, 'customfield_params[' . $row . '][calender]', '', 'value', 'text', $field->calender);

        $html = '
          <fieldset>
          <legend>Configure Files</legend>
          <table class="admintable"> <tr><td>Select File: </td><td>' . $media_files . "</td></tr>";
        $html .="<tr>"
                . "<td>Duration for <input class='input-small' type='text' name='customfield_params[" . $row . "][duration]' value='" . $field->duration . "'</td>"
                . "<td>" . $calender . "</td>"
                . "</tr>";
        $html .='</table>
          </fieldset>';

        $retValue .= $html;
        $row++;

        return true;
    }

    
    function plgVmOnDisplayProductFEVM3(&$product, &$group) {

        if ($group->custom_element != $this->_name)
            return '';

        if (!empty($group->media_id)) {
            foreach ($group->media_id as $id) {
                $media = $this->getMedia($id, array("file_title"));
                $group->display .= "<div>" . $media->file_title . "</div>";
            }
            $name = 'customProductData[' . $product->virtuemart_product_id . '][' . $group->virtuemart_custom_id . '][' . $group->virtuemart_customfield_id . '][' . $this->_name . ']';
            $group->display .='<input type="hidden" name="' . $name . '" value="' . $group->duration . ' ' . $group->calender . '">'; //without this Custom Field won't show up in cart page.
        }
        return true;
    }

    /**
     * Function for vm3
     * @see components/com_virtuemart/helpers/vmCustomPlugin::plgVmOnViewCart()
     * @author Patrick Kohl
     */
    function plgVmOnViewCart($product, $row, &$html) {
        return true;
    }

    /**
     * Trigger for VM3
     * @author Max Milbers
     * @param $product
     * @param $productCustom
     * @param $html
     * @return bool|string
     */
    function plgVmOnViewCartVM3(&$product, &$productCustom, &$html) {
        /* $customfields = new VirtueMartModelCustomfields();
          $allCustomfields = $customfields->getCustomEmbeddedProductCustomFields(array($product->virtuemart_product_id));
          foreach ($allCustomfields as $single) {
          if ($single->custom_element == $this->_name) {
          $productCustom = $single;
          break;
          }
          } */
        if (empty($productCustom->custom_element) or $productCustom->custom_element != $this->_name)
            return false;

		$html .= "<div>" . $productCustom->custom_title . "</div>";
        foreach ($productCustom->media_id as $id) {
            $media = $this->getMedia($id, array("file_title"));
            $html .= "<div>" . $media->file_title . "</div>";
        }
        return true;
    }

    function plgVmOnViewCartModuleVM3(&$product, &$productCustom, &$html) {
        return $this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    function plgVmDisplayInOrderBEVM3(&$product, &$productCustom, &$html) {
        $this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    function plgVmDisplayInOrderFEVM3(&$product, &$productCustom, &$html) {
        /* foreach ($product->customfields as $customfield) {
          if ($customfield->custom_element == $this->_name) {
          $productCustom = $customfield;
          break;
          }
          } */

        if (empty($productCustom->custom_element) or $productCustom->custom_element !== $this->_name)
            return FALSE;
		$vm_order_id = $this->getVmOrderID($product->virtuemart_order_item_id);
        $orderModel = new VirtueMartModelOrders();
        $order = $orderModel->getOrder($vm_order_id);
        $htmln = $html;
        $htmln .= "<div><b>" . $productCustom->custom_title . "</b></div>";
        foreach ($productCustom->media_id as $id) {
            $media = $this->getMedia($id, array("file_title"));
            $url = JURI::root() . 'index.php?option=com_virtuemart&view=plugin&format=html&name=' . $this->_name . '&media_id=' . $id . '&token=' . md5($order['details']['BT']->order_pass) . '&orderID=' . $vm_order_id;
            $htmln .= "<div>" . $media->file_title . "(<a href='" . $url . "'>Download</a>)</div>";
        }
		if($order['details']['BT']->order_status == "C"){
			if($productCustom->calender == "lifetime"){
				$htmln .="<div>Will never expire</div>";
			}else{
				$status = $this->checkDuration($order['details']['BT']->created_on, $productCustom->duration, $productCustom->calender);
				if($status >= 0){
					$htmln .="<div>Will expire in ".$status." day(s)</div>";
				}else{
					$htmln .="<div>Expired</div>";
				}
			}
			
		}
		echo $htmln;
		return true;
        //$this->plgVmOnViewCartVM3($product, $productCustom, $html);
    }

    /**
     *
     * vendor order display BE
     */
    function plgVmDisplayInOrderBE(&$item, $productCustom, &$html) {
        if (!empty($productCustom)) {
            $item->productCustom = $productCustom;
        }
        if (empty($item->productCustom->custom_element) or $item->productCustom->custom_element != $this->_name)
            return '';
		
		$vm_order_id = $this->getVmOrderID($item->virtuemart_order_item_id);
		$orderModel = new VirtueMartModelOrders();
        $order = $orderModel->getOrder($vm_order_id);
        $htmln = $html;
        $htmln .= "<div><b>" . $productCustom->custom_title . "</b></div>";
        foreach ($productCustom->media_id as $id) {
            $media = $this->getMedia($id, array("file_title"));
            $url = JURI::root() . 'index.php?option=com_virtuemart&view=plugin&format=html&name=' . $this->_name . '&media_id=' . $id . '&token=' . md5($order['details']['BT']->order_pass) . '&orderID=' . $vm_order_id;
            $htmln .= "<div>" . $media->file_title . "(<a href='" . $url . "'>Download</a>)</div>";
        }
		if($order['details']['BT']->order_status == "C"){
			if($productCustom->calender == "lifetime"){
				$htmln .="<div>Will never expire</div>";
			}else{
				$status = $this->checkDuration($order['details']['BT']->created_on, $productCustom->duration, $productCustom->calender);
				if($status >= 0){
					$htmln .="<div>Will expire in ".$status." day(s)</div>";
				}else{
					$htmln .="<div>Expired</div>";
				}
			}
			
		}
		echo $htmln;
		return true;
        //$this->plgVmOnViewCart($item, $productCustom, $html); //same render as cart
    }

    /**
     *
     * shopper order display FE
     */
    function plgVmDisplayInOrderFE(&$item, $productCustom, &$html) {
        if (!empty($productCustom)) {
            $item->productCustom = $productCustom;
        }
        if (empty($item->productCustom->custom_element) or $item->productCustom->custom_element != $this->_name)
            return '';
        $this->plgVmOnViewCart($item, $productCustom, $html); //same render as cart
    }

    /**
     * Trigger while storing an object using a plugin to create the plugin internal tables in case
     *
     * @author Max Milbers
     */
    public function plgVmOnStoreInstallPluginTable($psType, $data, $table) {

        if ($psType != $this->_psType)
            return false;
        if (empty($table->custom_element) or $table->custom_element != $this->_name) {
            return false;
        }
        if (empty($table->is_input)) {
            vmInfo('COM_VIRTUEMART_CUSTOM_IS_CART_INPUT_SET');
            $table->is_input = 1;
            $table->store();
        }
        //Should the textinput use an own internal variable or store it in the params?
        //Here is no getVmPluginCreateTableSQL defined
        //return $this->onStoreInstallPluginTable($psType);
    }

    /**
     * Declares the Parameters of a plugin
     * @param $data
     * @return bool
     */
    function plgVmDeclarePluginParamsCustomVM3(&$data) {

        return $this->declarePluginParams('custom', $data);
    }

    function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush) {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    function plgVmSetOnTablePluginParamsCustom($name, $id, &$table, $xParams) {
        return $this->setOnTablePluginParams($name, $id, $table, $xParams);
    }

    /**
     * Custom triggers note by Max Milbers
     */
    function plgVmOnDisplayEdit($virtuemart_custom_id, &$customPlugin) {
        return $this->onDisplayEditBECustom($virtuemart_custom_id, $customPlugin);
    }

    public function plgVmDisplayInOrderCustom(&$html, $item, $param, $productCustom, $row, $view = 'FE') {
        $this->plgVmDisplayInOrderCustom($html, $item, $param, $productCustom, $row, $view);
    }

    public function plgVmCreateOrderLinesCustom(&$html, $item, $productCustom, $row) {
// 		$this->createOrderLinesCustom($html,$item,$productCustom, $row );
    }

    function plgVmOnSelfCallFE($type, $name, &$render) {
        if ($name !== $this->_name)
            return false;

        $app = JFactory::getApplication();
        $input = $app->input;
        if (empty($input->get("orderID"))) {
            $app->enqueueMessage("Order Not Found", "error");
            return false;
        }
        $orderModel = new VirtueMartModelOrders();
        $order = $orderModel->getOrder($input->get("orderID"));
        $order_pass = md5($order['details']['BT']->order_pass);
        if (strcmp($order_pass, $input->get("token", "", "STRING")) == 0 && $order['details']['BT']->order_status == "C") {
            foreach ($order['items'] as $item) {
                foreach ($item->customfields as $customfield) {
                    if ($customfield->custom_element == $this->_name) {
                        $this->verifywithtime($order['details']['BT']->created_on, $input->get("media_id"), $customfield);
                        break;
                    }
                }
            }
        } else {
            $app->enqueueMessage("Either order's status is pending or invalid token ", "error");
            return false;
        }
    }

    protected function verifywithtime($created_on, $media_id, $customfield = array()) {
        $app = JFactory::getApplication();
        switch ($customfield->calender) {
            case "days":
                if ($this->checkDuration($created_on, $customfield->duration, "day") >= 0) {
                    $this->generateDownloadLink($media_id);
                } else {
                    $app->enqueueMessage("Your subscription has been expired", "error");
                }
                break;
            case "months":
                if ($this->checkDuration($created_on, $customfield->duration, "month") >= 0) {
                    $this->generateDownloadLink($media_id);
                } else {
                    $app->enqueueMessage("Your subscription has been expired", "error");
                }
                break;
            case "years":
                if ($this->checkDuration($created_on, $customfield->duration, "year") >= 0) {
                    $this->generateDownloadLink($media_id);
                } else {
                    $app->enqueueMessage("Your subscription has been expired", "error");
                }
                break;
            default:
                $this->generateDownloadLink($media_id);
                break;
        }
    }

    protected function checkDuration($created_on, $duration, $type) {

        $date = date_create($created_on);
        date_add($date, date_interval_create_from_date_string($duration . ' ' . $type));
        $newdate = date_format($date, 'Y-m-d');

        $current = date_create(date('Y-m-d', time()));
        $newdate = date_create($newdate);

        $interval = date_diff($current, $newdate);
        return $interval->format('%R%a');
    }

    protected function generateDownloadLink($media_id) {
        
		$file = $this->getMedia($media_id, array('file_mimetype', 'file_url'));
		$fd = fopen($file->file_url, 'r');
       
		header('Content-Type: ' . $file->file_mimetype);
		header("Content-disposition: attachment; filename=\"" . basename($file->file_url) . "\"");
        header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header("Content-Transfer-Encoding: binary");
		header('Pragma: public');
        
		while(!feof($fd)) {
			$buffer = fread($fd, 2048);
			echo $buffer;
			flush();
			ob_flush();
		}
    }
	
	protected function getMedia($id = "", $con = array()) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        if (empty($id)) {
            $query->select($db->quoteName(array('virtuemart_media_id', 'file_title')));
            $query->from($db->quoteName('#__virtuemart_medias'));
            $query->where($db->quoteName('file_is_forSale') . ' = ' . $db->quote('1'));
            $db->setQuery($query);
            return $db->loadAssocList();
        } elseif (!empty($id) && !empty($con)) {
            $query->select($db->quoteName($con));
            $query->from($db->quoteName('#__virtuemart_medias'));
            $query->where($db->quoteName('file_is_forSale') . ' = ' . $db->quote('1') . ' AND ' . $db->quoteName('virtuemart_media_id') . ' = ' . $db->quote($id));
            $db->setQuery($query);
            return $db->loadObject();
        } else {

        }
    }

    protected function getVmOrderID($virtuemart_order_item_id) {
        $db = JFactory::getDbo();
        $query = $db->setQuery("SELECT virtuemart_order_id FROM #__virtuemart_order_items WHERE virtuemart_order_item_id = '$virtuemart_order_item_id'");
		return $db->loadResult();
    }

}

// No closing tag