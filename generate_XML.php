<?php
	if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
		require_once(__DIR__.'/wp-load.php');
	}

	function generate_products_xml_data($format_price = false, $ifoutofstock = 1) {
	    $xml_rows = array();

	    if ($format_price) {
	    	if (function_exists('wc_get_price_decimal_separator') && function_exists('wc_get_price_thousand_separator') && function_exists('wc_get_price_decimals')) {
		        $decimal_separator = wc_get_price_decimal_separator();
		        $thousand_separator = wc_get_price_thousand_separator();
		        $decimals = wc_get_price_decimals();
		    }
		}

	    $result = wc_get_products(array('status' => array('publish'), 'limit' => -1));

	    foreach ($result as $index => $prod) {
	        $product_id = $prod->get_id();
	        $attributes = $prod->get_attributes();
	        $stockstatus = $prod->get_stock_status();

	        if ((strcmp($stockstatus, "outofstock") == 0) && ($ifoutofstock == 1)) {
	            continue;
	        }

	        $price = $prod->get_price();
	        $xml_rows[$product_id]['price_raw'] = $price;

	        if ($format_price) {
	            $price = number_format($price, $decimals, $decimal_separator, $thousand_separator);
	        }

	        $xml_rows[$product_id]['price'] = addslashes($price);
	        $xml_rows[$product_id]['stock_quantity'] = intval($prod->get_stock_quantity());
	        $image = get_the_post_thumbnail_url($product_id, 'shop_catalog');
	        $xml_rows[$product_id]['image_url'] = $image;
	        $xml_rows[$product_id]['images'] = array();
	        $attachment_ids = $prod->get_gallery_image_ids();

	        if (count($attachment_ids)) {
			    foreach($attachment_ids as $attachment_id) {
			        $xml_rows[$product_id]['images'][] = wp_get_attachment_url($attachment_id);
			    }
			}

	        $xml_rows[$product_id]['sku'] = $prod->get_sku();
	        $xml_rows[$product_id]['weight'] = $prod->get_weight();
	        $xml_rows[$product_id]['terms'] = array();

	        foreach ($attributes as $att_key => $prod_att) {
	            $xml_rows[$product_id]['terms'][$att_key] = array();
	            $prod_terms = $prod_att->get_terms();

	            foreach ($prod_terms as $the_term) {
	                $xml_rows[$product_id]['terms'][$att_key][] = $the_term->name;
	            }
	        }

	        $prod_category_tree = array_map('get_term', array_reverse(wc_get_product_cat_ids($product_id)));
	        $xml_rows[$product_id]['categories'] = array();
	        $category_path = '';

	        for ($i = 0; $i < count($prod_category_tree); $i++) {
	            if ($i == 0) {
	                $xml_rows[$product_id]['category_id'] = $prod_category_tree[$i]->term_id;
	            }

	            $category_path .= $prod_category_tree[$i]->name;
	            $xml_rows[$product_id]['categories'][] = $prod_category_tree[$i]->name;

	            if ($i < count($prod_category_tree) - 1) $category_path .= ', ';
	        }

	        $xml_rows[$product_id]['category_path'] = $category_path;
	        $title = str_replace("'", " ", $prod->get_title());
	        $title = str_replace("&", "+", $title);
	        $title = strip_tags($title);
	        $xml_rows[$product_id]['title'] = $title;
	        $xml_rows[$product_id]['description'] = $prod->get_short_description();
	        $xml_rows[$product_id]['manufacturer_code'] = get_post_meta($product_id, 'wppfm_product_gtin', true);
	        $xml_rows[$product_id]['manufacturer'] = get_post_meta($product_id, 'wppfm_product_brand', true);
	        $xml_rows[$product_id]['model'] = get_post_meta($product_id, 'wppfm_product_mpn', true);
	        $xml_rows[$product_id]['product_url'] = get_permalink($product_id);
	        $xml_rows[$product_id]['category_link'] = get_term_link($xml_rows[$product_id]['category_id'], 'product_cat');
	    }

	    return $xml_rows;
	}

	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<products>';

	$xml_rows = generate_products_xml_data(true, 0);
	
	if (count($xml_rows)) {
		foreach ($xml_rows as $product_id => $product_info) {
			$xml .= '<product id="'.$product_id.'">
				<title><![CDATA['.$product_info['title'].']]></title>
				<item_price><![CDATA['.$product_info['price'].']]></item_price>
				<manufacturer><![CDATA['.$product_info['manufacturer'].']]></manufacturer>
				<image_url><![CDATA['.$product_info['image_url'].']]></image_url>
				<product_url><![CDATA['.$product_info['product_url'].']]></product_url>
				<categories>';

			foreach ($product_info['categories'] as $cat_key => $cat_name) {
				$xml .= '<category><![CDATA['.$cat_name.']]></category>';
			}

			$xml .= '</categories>
				<description><![CDATA['.$product_info['description'].']]></description>
				<stock><![CDATA['.$product_info['stock_quantity'].']]></stock>
				<ean_code><![CDATA['.$product_info['sku'].']]></ean_code>
				<manufacturer_code><![CDATA['.$product_info['manufacturer_code'].']]></manufacturer_code>
				<model><![CDATA['.$product_info['model'].']]></model>';

			if (count($product_info['images'])) {
				$xml .= '<additional_images>';
				
				foreach ($product_info['images'] as $img_key => $img_url) {
					$xml .= '<image><![CDATA['.$img_url.']]></image>';
				}

				$xml .= '</additional_images>';
			}

			$xml .= '<delivery_time>3</delivery_time>
				<delivery_text>2 - 3 working days</delivery_text>
			</product>';
		}
	}

	$xml .= '</products>';
	$xml = str_replace("&nbsp;", " ", $xml);
	$dom = new DOMDocument('1.0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml);
	$xml_output = $dom->saveXML();

	ob_clean();
	
	header('Pragma: private');
	header('Cache-control: private, must-revalidate');
	header('Content-type: text/xml');
	header('Content-Disposition: attachment; filename="Products.xml"');
	
	echo $xml_output;
?>