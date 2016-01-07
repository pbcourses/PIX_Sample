#!/usr/bin/env php

<?PHP

/** 
* @author Matteo Piombo
*/ 

# Suppress warnings while leaving all other error reporting enabled
error_reporting(E_ALL ^ E_WARNING); 

	$csv = grab_rubrica("http://www.unipr.it/rubrica", 1);

	echo($csv);
	
	
	test_3_pages();
	
	
// ------------- Simple tests ------------------ //
	function test_3_pages() {
		
		$csv = grab_rubrica("http://www.unipr.it/rubrica", 3);
		
		$testStrings = array(
			"AGNESINI Prof. Alex,alex.agnesini@unipr.it,+39 0521 032552",
			"ALFIERI Prof. Roberto,roberto.alfieri@unipr.it,+39 0521 906214",
			"ARGIROPOULOS Prof. DIMITRIS,,"
		);
	
		foreach($testStrings as $test) {
			if (!contains($csv, $test)) {
				exit("Test Failed on: " . $test . PHP_EOL);
			}
		}
		
		echo "Passed all tests." . PHP_EOL;
	}

	
	// helper function to check if the haystack string contains the needle substring
	function contains($haystack, $needle) {
		if (is_bool(strpos($haystack, $needle))) {
			return False;
		} 
		
		return True;
	}
	
	
	
// ------------ Helper Functions and Data Types ------------ //

	function grab_rubrica($startURL, $maxPages) {
		$urlComopnents = parse_url($startURL);
		
		$baseUrl = $urlComopnents['scheme'] . "://" . $urlComopnents['host'];
		$rubricaHomeURL = $baseUrl . "/rubrica";
	
		$csv = "";
		$url = $rubricaHomeURL;
		for ($i = 0 ; $i < $maxPages; $i++) {
			$data = get_rubrica_data_from_URL($url);
			$csv = $csv . $data->csvData;
		
			if (strlen($csv) == 0) {
				// No csv data found, terminate the loop
			}
		
			if(is_null($data->nextPage)) {
				// No next page found, terminate the loop
				break;
			}
		
			$url = $baseUrl . $data->nextPage;
		
			sleep(4);
		}
	
		return $csv;
	}
	
	

	class PageData {
		public $csvData;	// page data as csv string
		public $nextPage;	// next page relative url
	}
	
/** 
* get_rubrica_data_from_URL(string $url)
* 
* @param string $url 
* @return PageData object 
*/ 
	function get_rubrica_data_from_URL($url) {
		
		$pageData = new PageData();
		$pageData->csvData = "";
		$pageData->nextPage = NULL;
		
		$pageHTML = file_get_contents($url);
		$pageDom = new DOMDocument;
		$pageDom->loadHTML($pageHTML);
		$pageXpath = new DOMXPath($pageDom);
		
		$rubricaView = get_rubrica_view_node($pageDom);
		
		if (!is_null($rubricaView)) {

			$rubricaRows = get_rubrica_table_rows($pageXpath, $rubricaView);

			foreach($rubricaRows as $row) {
				$parsedRow = get_rubrica_entry($pageXpath, $row);
				$pageData->csvData = $pageData->csvData . $parsedRow . PHP_EOL;
			}
		}
		
		// Get next page relative URL
		$pageData->nextPage = get_next_page_relative_URL($pageXpath, $rubricaView);
		
		return $pageData;
	}
	

/** 
* get_rubrica_view_node
* 
* @param DOMDocument $pageDom 
* @return DOMNode 
*
*	Returns the DOMNode containing the entire rubrica's view of interest
*	(i.e.: table and pager)
*/ 
	function get_rubrica_view_node($pageDom) {
		$rubricaH1Title = "Elenco telefonico";
		$count = 0;
		foreach($pageDom->getElementsByTagName('h1') as $header1) { 
			if ($header1->textContent == $rubricaH1Title) {
				return $header1->parentNode;
			}
    	}
    	return NULL;
	}
	
	
/** 
* get_rubrica_table_rows
* 
* @param DOMXpath $pageXpath
* @param DOMNode $viewNode 
* @return DOMNodeList 
*
*	returns the DONNodeLiist of the rows in rubrica's table body
*/
	function get_rubrica_table_rows($pageXpath, $viewNode) {
		// Query for table body rows in rubrica table
		$query = "//table[contains(@class,'views-table') and contains(@class ,'cols-4')]//tbody/tr";
		return $pageXpath->query($query, $viewNode);
	}
	
	
/** 
* get_rubrica_entry
* 
* @param DOMXpath $pageXpath
* @param DOMNode $rubricaRowNode 
* @return string
*
*	Extract data from a rubrica's table row.
*	Returns a string in csv format:
*	
*	Full Name , Email , Phone Number
*/
	function get_rubrica_entry($pageXpath, $rubricaRowNode) {
		
		// get the name
		// Assume is first cell always has full text content as the name
		$nameQuery = "td[1]/text()";
		$name = $pageXpath->query($nameQuery, $rubricaRowNode)
					->item(0)
					->textContent;
		$name = trim($name);
							
		// get email address
		// Assume first link is email and its text is the address
		$emailQuery = "td[2]/a";
		$emailNode = $pageXpath->query($emailQuery, $rubricaRowNode);
		$email = "";
		if ($emailNode->length > 0) {
			$email = trim($emailNode->item(0)->textContent);
		}
					
		// get phone number
		// Assume phone number is text content after a given phone text prefix
		$phoneQuery = "td[2]";
		$phone = $pageXpath->query($phoneQuery, $rubricaRowNode)
					->item(0)
					->textContent;
		$phonePrefix = "Telefono: ";
		$phonePosition = strrpos($phone, $phonePrefix);
		if (is_bool($phonePosition)) {
			// No phone position found
			$phone = $phone;
		} else {
			$phone = substr($phone, $phonePosition+strlen($phonePrefix));
		}
		$phone = trim($phone);
		
		return $name . "," . $email . "," . $phone;
	}
	
/** 
* get_next_page_relative_URL
* 
* @param DOMXpath $pageXpath
* @param DOMNode $viewNode 
* @return string or NULL
*
*	Attempts to find the next page in the rubrica DOMNode, will returns NULL if it can't find the next page.
*/	
	function get_next_page_relative_URL($pageXpath, $viewNode) {
		$nextPageQuery = "//ul[contains(@class,'pager') and contains(@class,'clearfix')]//li[@class='pager-next']/a[1]";
		$pager = $pageXpath->query($nextPageQuery, $viewNode);
		if ($pager->length > 0) {
			return $pager->item(0)->getAttribute('href');
		} else {
			return NULL;
		}
	}
?>