<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2026		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    geotiers/class/actions_geotiers.class.php
 * \ingroup geotiers
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsGeoTiers
 */
class ActionsGeoTiers extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();


	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array<string,mixed>	$parameters	Array of parameters
	 * @param	CommonObject		$object		The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action		'add', 'update', 'view'
	 * @return	int								Return integer <0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *											>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overload the doActions function : replacing the parent's function with the one below
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	?string				$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters = false, &$object, &$action = '')
	{
		$error = 0;
		$TContext = explode(':', $parameters['context']);

		if (in_array('thirdpartycard', $TContext)) {
			$address = trim(GETPOST('address', 'restricthtml'));
			$zip     = trim(GETPOST('zip', 'alphanohtml'));
			$town    = trim(GETPOST('town', 'alphanohtml'));

			if (in_array($action, array('add', 'create', 'update', 'edit'))) {
				$hasAddress = !empty($address) || !empty($zip) || !empty($town);

				if ($hasAddress) {
					$fullAddress = trim($address.' '.$zip.' '.$town);

					$coords = $this->fetchGps($fullAddress);

					if (!empty($coords['lat']) && !empty($coords['long'])) {

						$geo = $coords['lat'].', '.$coords['long'];

						$_POST['options_fl_geo'] = $geo;

						$object->array_options['options_fl_geo'] = $geo;
					}
				}
			}

			return $error ? -1 : 0;
		}

		return 0;
	}

	/**
	 * Récupère latitude / longitude depuis l'API geopf
	 *
	 * @param string $address
	 * @return array
	 */
	private function fetchGps($address)
	{
		$url = 'https://data.geopf.fr/geocodage/search?q='.urlencode($address);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($response === false || !empty($curlError) || (int) $httpCode !== 200) {
			dol_syslog(__METHOD__.' geopf http error code='.$httpCode.' error='.$curlError, LOG_WARNING);
			return array('lat' => null, 'long' => null, 'citycode' => null);
		}

		$data = json_decode($response, true);
		if (empty($data['features']) || empty($data['features'][0]['geometry']['coordinates'])) {
			dol_syslog(__METHOD__.' geopf no result for address='.$address, LOG_WARNING);
			return array('lat' => null, 'long' => null, 'citycode' => null);
		}

		$coordinates = $data['features'][0]['geometry']['coordinates'];
		$longitude = isset($coordinates[0]) ? $coordinates[0] : null;
		$latitude  = isset($coordinates[1]) ? $coordinates[1] : null;
		$citycode  = !empty($data['features'][0]['properties']['citycode']) ? $data['features'][0]['properties']['citycode'] : null;

		return array(
			'lat' => $latitude,
			'long' => $longitude,
			'citycode' => $citycode,
		);
	}


	/**
	 * Overload the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	?string				$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$massaction = GETPOST('massaction', 'aZ09');

		dol_syslog(__METHOD__.' context='.$parameters['context'].' action='.$action.' massaction='.$massaction, LOG_WARNING);

		if (
			in_array('thirdpartylist', explode(':', $parameters['context']))
			&& $massaction === 'geolocalize'
		) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

			$countOk = 0;
			$countKo = 0;

			foreach ($parameters['toselect'] as $i => $objectid) {
				$societe = new Societe($this->db);

				if ($societe->fetch((int) $objectid) <= 0) {
					dol_syslog(__METHOD__.' FETCH FAILED for id='.$objectid, LOG_WARNING);
					$countKo++;
					continue;
				}

				$fullAddress = trim($societe->address.' '.$societe->zip.' '.$societe->town);
				dol_syslog(__METHOD__.' address="'.$fullAddress.'" for id='.$objectid, LOG_DEBUG);

				if (empty($fullAddress)) {
					dol_syslog(__METHOD__.' EMPTY ADDRESS for id='.$objectid, LOG_WARNING);
					$countKo++;
					continue;
				}

				$coords = $this->fetchGps($fullAddress);
				dol_syslog(__METHOD__.' coords='.json_encode($coords).' for id='.$objectid, LOG_DEBUG);

				if (!empty($coords['lat']) && !empty($coords['long'])) {
					$geo = $coords['lat'].', '.$coords['long'];

					$societe->array_options['options_fl_geo'] = $geo;
					$res = $societe->insertExtraFields();

					dol_syslog(__METHOD__.' insertExtraFields res='.$res.' geo='.$geo.' for id='.$objectid, LOG_DEBUG);

					if ($res < 0) {
						dol_syslog(__METHOD__.' INSERT FAILED: '.implode(', ', $societe->errors), LOG_WARNING);
						$countKo++;
					} else {
						$countOk++;
					}
				} else {
					dol_syslog(__METHOD__.' NO COORDS for id='.$objectid, LOG_WARNING);
					$countKo++;
				}

				if ($i > 0 && $i % 10 === 0) {
					usleep(500000); 
				}
			}

			$this->resprints = 'Géolocalisation : '.$countOk.' OK, '.$countKo.' KO';
			return 0;
		}

		return 0;
	}


	/**
	 * Overload the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param	array<string,mixed>	$parameters     Hook metadata (context, etc...)
	 * @param	CommonObject		$object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	?string	$action						Current action (if set). Generally create or edit or null
	 * @param	HookManager	$hookmanager			Hook manager propagated to allow calling another hook
	 * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$error = 0;

		if (in_array('thirdpartylist', explode(':', $parameters['context']))) {
			$this->resprints = '<option value="geolocalize">' .$langs->trans("GeoTiersMassAction").'</option>';
		}

		if (!$error) {
			return 0;
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action before PDF (document) creation
	 *
	 * @param	array<string,mixed>	$parameters	Array of parameters
	 * @param	CommonObject		$object		Object output on PDF
	 * @param	string				$action		'add', 'update', 'view'
	 * @return	int								Return integer <0 if KO,
	 *											=0 if OK but we want to process standard actions too,
	 *											>0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		// @phan-suppress-next-line PhanPluginEmptyStatementIf
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action after PDF (document) creation
	 *
	 * @param	array<string,mixed>	$parameters	Array of parameters
	 * @param	CommonDocGenerator	$pdfhandler	PDF builder handler
	 * @param	string				$action		'add', 'update', 'view'
	 * @return	int								Return integer <0 if KO,
	 * 											=0 if OK but we want to process standard actions too,
	 *											>0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		// @phan-suppress-next-line PhanPluginEmptyStatementIf
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overload the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
	 * @param	?string				$action 		Current action (if set). Generally create or edit or null
	 * @param	HookManager			$hookmanager    Hook manager propagated to allow calling another hook
	 * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $langs;

		$langs->load("geotiers@geotiers");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'geotiers') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("GeoTiers");
			$this->results['picto'] = 'geotiers@geotiers';
		}

		$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily']) ? '' : '&tabfamily='.$parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		$arrayoftypes = array();
		//$arrayoftypes['geotiers_myobject'] = array('label' => 'MyObject', 'picto'=>'myobject@geotiers', 'ObjectClassName' => 'MyObject', 'enabled' => isModEnabled('geotiers'), 'ClassPath' => "/geotiers/class/myobject.class.php", 'langs'=>'geotiers@geotiers')

		$this->results['arrayoftype'] = $arrayoftypes;

		return 0;
	}



	/**
	 * Overload the restrictedArea function : check permission on an object
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
	 * @param   CommonObject    	$object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, $object, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->hasRight('geotiers', 'myobject', 'read')) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// used to make some tabs removed
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('geotiers@geotiers');
			// used when we want to add some tabs
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/geotiers/geotiers_tab.php', 1) . '?id=' . $id . '&amp;module='.$element;
				$parameters['head'][$counter][1] = $langs->trans('GeoTiersTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'geotiersemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {  // @phpstan-ignore-line
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// From V14 onwards, $parameters['head'] is modifiable by reference
				return 0;
			}
		} else {
			// Bad value for $parameters['mode']
			return -1;
		}
	}


	/**
	 * Overload the showLinkToObjectBlock function : add or replace array of object linkable
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata (context, etc...)
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	?string				$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									Return integer < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		$myobject = new MyObject($object->db);
		$this->results = array('myobject@geotiers' => array(
			'enabled' => isModEnabled('geotiers'),
			'perms' => 1,
			'label' => 'LinkToMyObject',
			'sql' => "SELECT t.rowid, t.ref, t.ref as 'name' FROM " . $this->db->prefix() . $myobject->table_element. " as t "),);

		return 1;
	}
	/* Add other hook methods here... */
}
