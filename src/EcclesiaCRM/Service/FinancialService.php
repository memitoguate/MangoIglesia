<?php

namespace EcclesiaCRM\Service;

require_once dirname(dirname(__FILE__)).'/../Include/Functions.php';

use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\MICRReader;
use EcclesiaCRM\PledgeQuery;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\SessionUser;
use EcclesiaCRM\Utils\MiscUtils;

class FinancialService
{
    public function processAuthorizeNet()
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        global $cnInfoCentral;
        $donation = new \AuthorizeNetAIM();
        $donation->amount = "$plg_amount";
        $donation->first_name = $firstName;
        $donation->last_name = $lastName;
        $donation->address = $address1.$address2;
        $donation->city = $city;
        $donation->state = $state;
        $donation->zip = $zip;
        $donation->country = $country;
        $donation->description = 'UU Nashua Pledge';
        $donation->email = $email;
        $donation->phone = $phone;

    // not setting these
    //        $donation->allow_partial_auth
    //        $donation->auth_code
    //        $donation->authentication_indicator
    //        $donation->bank_aba_code
    //        $donation->bank_check_number
    //        $donation->card_code
    //        $donation->cardholder_authentication_value
    //        $donation->company
    //        $donation->cust_id
    //        $donation->customer_ip
    //        $donation->delim_char
    //        $donation->delim_data
    //        $donation->duplicate_window
    //        $donation->duty
    //        $donation->echeck_type
    //        $donation->email_customer
    //        $donation->encap_char
    //        $donation->fax
    //        $donation->footer_email_receipt
    //        $donation->freight
    //        $donation->header_email_receipt
    //        $donation->invoice_num
    //        $donation->line_item
    //        $donation->login
    //        $donation->method
    //        $donation->po_num
    //        $donation->recurring_billing
    //        $donation->relay_response
    //        $donation->ship_to_address
    //        $donation->ship_to_city
    //        $donation->ship_to_company
    //        $donation->ship_to_country
    //        $donation->ship_to_first_name
    //        $donation->ship_to_last_name
    //        $donation->ship_to_state
    //        $donation->ship_to_zip
    //        $donation->split_tender_id
    //        $donation->tax
    //        $donation->tax_exempt
    //        $donation->test_request
    //        $donation->tran_key
    //        $donation->trans_id
    //        $donation->type
    //        $donation->version

    if ($dep_Type == 'CreditCard') {
        $donation->card_num = $creditCard;
        $donation->exp_date = $expMonth.'/'.$expYear;
    } else {
        // check payment info if supplied...

      // Use eCheck:
      $donation->bank_acct_name = $firstName.' '.$lastName;
        $donation->bank_acct_num = $account;
        $donation->bank_acct_type = 'CHECKING';
        $donation->bank_name = $bankName;

        $donation->setECheck(
        $route,
        $account,
        'CHECKING',
        $bankName,
        $firstName.' '.$lastName,
        'WEB'
      );
    }

        $response = $donation->authorizeAndCapture();
        if ($response->approved) {
            $transaction_id = $response->transaction_id;
        }

        if ($response->approved) {
            // Push the authorized transaction date forward by the interval
      $sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('".$authDate."', INTERVAL ".$aut_Interval.' MONTH) WHERE aut_ID = '.$aut_ID.' AND aut_Amount = '.$plg_amount;
            MiscUtils::RunQuery($sSQL);
      // Update the serial number in any case, even if this is not the scheduled payment
      $sSQL = 'UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = '.$aut_ID;
            MiscUtils::RunQuery($sSQL);
        }

        if (!($response->approved)) {
            $response->approved = 0;
        }

        $sSQL = 'UPDATE pledge_plg SET plg_aut_Cleared='.$response->approved.' WHERE plg_plgID='.$plg_plgID;
        MiscUtils::RunQuery($sSQL);

        if ($plg_aut_ResultID) {
            // Already have a result record, update it.
      $sSQL = 'UPDATE result_res SET '.
        "res_echotype1    ='".$response->response_reason_code."',".
        "res_echotype2    ='".$response->response_reason_text."',".
        "res_echotype3    ='".$response->response_code."',".
        "res_authorization    ='".$response->response_subcode."',".
        "res_order_number    ='".$response->authorization_code."',".
        "res_reference    ='".$response->avs_response."',".
        "res_status    ='".$response->transaction_id."'".
        ' WHERE res_ID='.$plg_aut_ResultID;
            MiscUtils::RunQuery($sSQL);
        } else {
            // Need to make a new result record
      $sSQL = 'INSERT INTO result_res (
                                    res_echotype1,
                                    res_echotype2,
                                    res_echotype3,
                                    res_authorization,
                                    res_order_number,
                                    res_reference,
                                    res_status)
                                VALUES ('.
        "'".mysqli_real_escape_string($cnInfoCentral, $response->response_reason_code)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->response_reason_text)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->response_code)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->response_subcode)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->authorization_code)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->avs_response)."',".
        "'".mysqli_real_escape_string($cnInfoCentral, $response->transaction_id)."')";
            MiscUtils::RunQuery($sSQL);

      // Now get the ID for the newly created record
      $sSQL = 'SELECT MAX(res_ID) AS iResID FROM result_res';
            $rsLastEntry = MiscUtils::RunQuery($sSQL);
            extract(mysqli_fetch_array($rsLastEntry));
            $plg_aut_ResultID = $iResID;

      // Poke the ID of the new result record back into this pledge (payment) record
      $sSQL = 'UPDATE pledge_plg SET plg_aut_ResultID='.$plg_aut_ResultID.' WHERE plg_plgID='.$plg_plgID;
            MiscUtils::RunQuery($sSQL);
        }
    }

    public function processVanco()
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $customerid = "$aut_ID";  // This is an optional value that can be used to indicate a unique customer ID that is used in your system
    // put aut_ID into the $customerid field
    // Create object to preform API calls

    $workingobj = new \VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
    // Call Login API to receive a session ID to be used in future API calls
    $sessionid = $workingobj->vancoLoginRequest();
    // Create content to be passed in the nvpvar variable for a TransparentRedirect API call
    $nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator($VancoUrltoredirect, $customerid, '', 'NO');

        $paymentmethodref = '';
        if ($dep_Type == 'CreditCard') {
            $paymentmethodref = $creditcardvanco;
        } else {
            $paymentmethodref = $accountvanco;
        }

        $addRet = $workingobj->vancoEFTAddCompleteTransactionRequest(
      $sessionid, // $sessionid
      $paymentmethodref,// $paymentmethodref
      '0000-00-00',// $startdate
      'O',// $frequencycode
      $customerid,// $customerid
      '',// $customerref
      $firstName.' '.$lastName,// $name
      $address1,// $address1
      $address2,// $address2
      $city,// $city
      $state,// $state
      $zip,// $czip
      $phone,// $phone
      'No',// $isdebitcardonly
      '',// $enddate
      '',// $transactiontypecode
      '',// $funddict
      $plg_amount); // $amount

    $retArr = [];
        parse_str($addRet, $retArr);

        $errListStr = '';
        if (array_key_exists('errorlist', $retArr)) {
            $errListStr = $retArr['errorlist'];
        }

        $bApproved = false;

    // transactionref=None&paymentmethodref=16610755&customerref=None&requestid=201411222041237455&errorlist=167
    if ($retArr['transactionref'] != 'None' && $errListStr == '') {
        $bApproved = true;
    }

        $errStr = '';
        if ($errListStr != '') {
            $errList = explode(',', $errListStr);
            foreach ($errList as $oneErr) {
                $errStr .= $workingobj->errorString($oneErr."<br>\n");
            }
        }
        if ($errStr == '') {
            $errStr = 'Success: Transaction reference number '.$retArr['transactionref'].'<br>';
        }

        if ($bApproved) {
            // Push the authorized transaction date forward by the interval
      $sSQL = "UPDATE autopayment_aut SET aut_NextPayDate=DATE_ADD('".$authDate."', INTERVAL ".$aut_Interval.' MONTH) WHERE aut_ID = '.$aut_ID.' AND aut_Amount = '.$plg_amount;
            MiscUtils::RunQuery($sSQL);
      // Update the serial number in any case, even if this is not the scheduled payment
      $sSQL = 'UPDATE autopayment_aut SET aut_Serial=aut_Serial+1 WHERE aut_ID = '.$aut_ID;
            MiscUtils::RunQuery($sSQL);
        }

        $sSQL = "UPDATE pledge_plg SET plg_aut_Cleared='".$bApproved."' WHERE plg_plgID=".$plg_plgID;
        MiscUtils::RunQuery($sSQL);

        if ($plg_aut_ResultID) {
            // Already have a result record, update it.

      $sSQL = "UPDATE result_res SET res_echotype2='".mysqli_real_escape_string($cnInfoCentral, $errStr)."' WHERE res_ID=".$plg_aut_ResultID;
            MiscUtils::RunQuery($sSQL);
        } else {
            // Need to make a new result record
      $sSQL = "INSERT INTO result_res (res_echotype2) VALUES ('".mysqli_real_escape_string($cnInfoCentral, $errStr)."')";
            MiscUtils::RunQuery($sSQL);

      // Now get the ID for the newly created record
      $sSQL = 'SELECT MAX(res_ID) AS iResID FROM result_res';
            $rsLastEntry = MiscUtils::RunQuery($sSQL);
            extract(mysqli_fetch_array($rsLastEntry));
            $plg_aut_ResultID = $iResID;

      // Poke the ID of the new result record back into this pledge (payment) record
      $sSQL = 'UPDATE pledge_plg SET plg_aut_ResultID='.$plg_aut_ResultID.' WHERE plg_plgID='.$plg_plgID;
            MiscUtils::RunQuery($sSQL);
        }
    }

    public function deletePayment($groupKey)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        PledgeQuery::create()->findByGroupkey($groupKey)->delete();
    }

    public function getMemberByScanString($sstrnig)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        if (SystemConfig::getValue('bUseScannedChecks')) {
            require '../Include/MICRFunctions.php';
            $micrObj = new MICRReader(); // Instantiate the MICR class
      $routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching
      if ($routeAndAccount) {
          $sSQL = 'SELECT fam_ID, fam_Name FROM family_fam WHERE fam_scanCheck="'.$routeAndAccount.'"';
          $rsFam = MiscUtils::RunQuery($sSQL);
          extract(mysqli_fetch_array($rsFam));
          $iCheckNo = $micrObj->FindCheckNo($tScanString);

          return '{"ScanString": "'.$tScanString.'" , "RouteAndAccount": "'.$routeAndAccount.'" , "CheckNumber": "'.$iCheckNo.'" ,"fam_ID": "'.$fam_ID.'" , "fam_Name": "'.$fam_Name.'"}';
      } else {
          throw new \Exception('error in locating family');
      }
        } else {
            throw new \Exception('Scanned Checks is disabled');
        }
    }

    public function setDeposit($depositType, $depositComment, $depositDate, $iDepositSlipID = null, $depositClosed = false)
    {
        if ($iDepositSlipID) {
            $sSQL = "UPDATE deposit_dep SET dep_Date = '".$depositDate."', dep_Comment = '".$depositComment."', dep_EnteredBy = ".SessionUser::getUser()->getPersonId().', dep_Closed = '.intval($depositClosed).' WHERE dep_ID = '.$iDepositSlipID.';';
            $bGetKeyBack = false;
            if ($depositClosed && ($depositType == 'CreditCard' || $depositType == 'BankDraft')) {
                // Delete any failed transactions on this deposit slip now that it is closing
        $q = 'DELETE FROM pledge_plg WHERE plg_depID = '.$iDepositSlipID.' AND plg_PledgeOrPayment="Payment" AND plg_aut_Cleared=0';
                MiscUtils::RunQuery($q);
            }
            MiscUtils::RunQuery($sSQL);
        } else {
            $sSQL = "INSERT INTO deposit_dep (dep_Date, dep_Comment, dep_EnteredBy,  dep_Type)
            VALUES ('".$depositDate."','".$depositComment."',".SessionUser::getUser()->getPersonId().",'".$depositType."')";
            MiscUtils::RunQuery($sSQL);
            $sSQL = 'SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep';
            $rsDepositSlipID = MiscUtils::RunQuery($sSQL);
            $iDepositSlipID = mysqli_fetch_array($rsDepositSlipID)[0];
        }
        $_SESSION['iCurrentDeposit'] = $iDepositSlipID;

        return $this->getDeposits($iDepositSlipID);
    }

    public function getDepositTotal($id, $type = null)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $sqlClause = '';
        if ($type) {
            $sqlClause = "AND plg_method = '".$type."'";
        }
    // Get deposit total
    $sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$id' AND plg_PledgeOrPayment = 'Payment' ".$sqlClause;
        $rsDepositTotal = MiscUtils::RunQuery($sSQL);
        list($deposit_total) = mysqli_fetch_row($rsDepositTotal);

        return $deposit_total;
    }

    public function getPaymentJSON($payments)
    {
        if ($payments) {
            return '{"payments":'.json_encode($payments).'}';
        } else {
            return false;
        }
    }

    public function getPayments($depID)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $sSQL = 'SELECT * from pledge_plg
            INNER JOIN
            donationfund_fun
            ON
            pledge_plg.plg_fundID = donationfund_fun.fun_ID';

        if ($depID) {
            $sSQL .= ' WHERE plg_depID = '.$depID;
        }
        $rsDep = MiscUtils::RunQuery($sSQL);

        $payments = [];
        while ($aRow = mysqli_fetch_array($rsDep)) {
            extract($aRow);
            $family = FamilyQuery::create()->findOneById($plg_FamID);
            $values = new \stdClass();
            $values->plg_plgID = $plg_plgID;
            $values->plg_FamID = $plg_FamID;
            $values->familyString = $family->getFamilyString();
            $values->plg_FYID = $plg_FYID;
            $values->FiscalYear = MiscUtils::MakeFYString($plg_FYID);
            $values->plg_date = $plg_date;
            $values->plg_amount = $plg_amount;
            $values->plg_schedule = $plg_schedule;
            $values->plg_method = $plg_method;
            $values->plg_comment = $plg_comment;
            $values->plg_DateLastEdited = $plg_DateLastEdited;
            $values->plg_EditedBy = $plg_EditedBy;
            $values->plg_PledgeOrPayment = $plg_PledgeOrPayment;
            $values->plg_fundID = $plg_fundID;
            $values->fun_Name = $fun_Name;
            $values->plg_depID = $plg_depID;
            $values->plg_CheckNo = $plg_CheckNo;
            $values->plg_Problem = $plg_Problem;
            $values->plg_scanString = $plg_scanString;
            $values->plg_aut_ID = $plg_aut_ID;
            $values->plg_aut_Cleared = $plg_aut_Cleared;
            $values->plg_aut_ResultID = $plg_aut_ResultID;
            $values->plg_NonDeductible = $plg_NonDeductible;
            $values->plg_GroupKey = $plg_GroupKey;

            array_push($payments, $values);
        }

        return $payments;
    }

    public function searchDeposits($searchTerm)
    {
        $searchTerm = filter_var($searchTerm, FILTER_SANITIZE_STRING);

        MiscUtils::requireUserGroupMembership('bFinance');
        $fetch = 'SELECT dep_ID, dep_Comment, dep_Date, dep_EnteredBy, dep_Type
            FROM deposit_dep
            LEFT JOIN pledge_plg ON
                pledge_plg.plg_depID = deposit_dep.dep_ID
                AND
                plg_CheckNo LIKE \'%'.$searchTerm.'%\'
            WHERE
            dep_Comment LIKE \'%'.$searchTerm.'%\'
            OR
            dep_Date LIKE \'%'.$searchTerm.'%\'
            OR
            plg_CheckNo LIKE \'%'.$searchTerm.'%\'
            LIMIT 15';

        $result = MiscUtils::RunQuery($fetch);
        $deposits = [];
        while ($row = mysqli_fetch_array($result)) {
            $row_array['id'] = $row['dep_ID'];
            $row_array['displayName'] = $row['dep_Comment'].' - '.$row['dep_Date'];
            $row_array['uri'] = $this->getViewURI($row['dep_ID']);
            array_push($deposits, $row_array);
        }

        return $deposits;
    }

    public function searchPayments($searchTerm)
    {
        $searchTerm = filter_var($searchTerm, FILTER_SANITIZE_STRING);

        MiscUtils::requireUserGroupMembership('bFinance');
        $fetch = 'SELECT dep_ID, dep_Comment, dep_Date, dep_EnteredBy, dep_Type, plg_FamID, plg_amount, plg_CheckNo, plg_plgID, plg_GroupKey
            FROM deposit_dep
            LEFT JOIN pledge_plg ON
                pledge_plg.plg_depID = deposit_dep.dep_ID
            WHERE
            plg_CheckNo LIKE \'%'.$searchTerm.'%\'
            LIMIT 15';

        $result = MiscUtils::RunQuery($fetch);

        $deposits = [];
        while ($row = mysqli_fetch_array($result)) {
            $family = FamilyQuery::create()->findOneById($row['plg_FamID']);
            $row_array['id'] = $row['dep_ID'];
            $row_array['displayName'] = 'Check #'.$row['plg_CheckNo'].': '.$family->getName().' - '.$row['dep_Date'];
            $row_array['uri'] = $this->getPaymentViewURI($row['plg_GroupKey']);
            array_push($deposits, $row_array);
        }

        return $deposits;
    }

    public function getPaymentViewURI($groupKey)
    {
        return SystemURLs::getRootPath().'/v2/deposit/pledge/editor/GroupKey/'.$groupKey;
    }

    public function getViewURI($Id)
    {
        return SystemURLs::getRootPath().'/v2/deposit/slipeditor/'.$Id;
    }

    private function validateDate($payment)
    {
        // Validate Date
    if (strlen($payment->Date) > 0) {
        list($iYear, $iMonth, $iDay) = sscanf($payment->Date, '%04d-%02d-%02d');
        if (!checkdate($iMonth, $iDay, $iYear)) {
            throw new \Exception('Invalid Date');
        }
    }
    }

    private function validateFund($payment)
    {
        //Validate that the fund selection is valid:
    //If a single fund is selected, that fund must exist, and not equal the default "Select a Fund" selection.
    //If a split is selected, at least one fund must be non-zero, the total must add up to the total of all funds, and all funds in the split must be valid funds.
    $FundSplit = json_decode($payment->FundSplit);
        if (count($FundSplit) >= 1 and $FundSplit[0]->FundID != 'None') { // split
      $nonZeroFundAmountEntered = 0;
            foreach ($FundSplit as $fun_id => $fund) {
                //$fun_active = $fundActive[$fun_id];
        if ($fund->Amount > 0) {
            ++$nonZeroFundAmountEntered;
        }
                if (SystemConfig::getValue('bEnableNonDeductible') && isset($fund->NonDeductible)) {
                    //Validate the NonDeductible Amount
          if ($fund->NonDeductible > $fund->Amount) { //Validate the NonDeductible Amount
            throw new \Exception(_("NonDeductible amount can't be greater than total amount."));
          }
                }
            } // end foreach
      if (!$nonZeroFundAmountEntered) {
          throw new \Exception(_('At least one fund must have a non-zero amount.'));
      }
        } else {
            throw new \Exception('Must select a valid fund');
        }
    }

    public function locateFamilyCheck($checkNumber, $fam_ID)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $sSQL = 'SELECT count(plg_FamID) from pledge_plg
                 WHERE plg_CheckNo = '.$checkNumber.' AND
                 plg_FamID = '.$fam_ID;
        $rCount = MiscUtils::RunQuery($sSQL);

        return mysqli_fetch_array($rCount)[0];
    }

    public function validateChecks($payment)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
    //validate that the payment options are valid
    //If the payment method is a check, then the check nubmer must be present, and it must not already have been used for this family
    //if the payment method is cash, there must not be a check number
    if ($payment->type == 'Payment' and $payment->iMethod == 'CHECK' and !isset($payment->iCheckNo)) {
        throw new \Exception(_('Must specify non-zero check number'));
    }
    // detect check inconsistencies
    if ($payment->type == 'Payment' and isset($payment->iCheckNo)) {
        if ($payment->iMethod == 'CASH') {
            throw new \Exception(_("Check number not valid for 'CASH' payment"));
        } //build routine to make sure this check number hasn't been used by this family yet (look at group key)
      elseif ($payment->iMethod == 'CHECK' and $this->locateFamilyCheck($payment->iCheckNo, $payment->FamilyID)) {
          throw new \Exception("Check number '".$payment->iCheckNo."' for selected family already exists.");
      }
    }
    }

    public function processCurrencyDenominations($payment, $groupKey)
    {
        $currencyDenoms = json_decode($payment->cashDenominations);
        foreach ($currencyDenoms as $cdom) {
            $sSQL = "INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, plg_depID, pdem_denominationID, pdem_denominationQuantity)
      VALUES ('".$groupKey."','".$payment->DepositID."','".$cdom->currencyID."','".$cdom->Count."')";
            if (isset($sSQL)) {
                MiscUtils::RunQuery($sSQL);
                unset($sSQL);
            }
        }
    }

    public function insertPledgeorPayment($payment)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
    // Only set PledgeOrPayment when the record is first created
    // loop through all funds and create non-zero amount pledge records
    unset($sGroupKey);
        $FundSplit = json_decode($payment->FundSplit);
        foreach ($FundSplit as $Fund) {
            if ($Fund->Amount > 0) {  //Only insert a row in the pledge table if this fund has a non zero amount.
        if (!isset($sGroupKey)) {  //a GroupKey references a single familie's payment, and transcends the fund splits.  Sharing the same Group Key for this payment helps clean up reports.
          if ($payment->iMethod == 'CHECK') {
              $sGroupKey = MiscUtils::genGroupKey($payment->iCheckNo, $payment->FamilyID, $Fund->FundID, $payment->Date);
          } elseif ($payment->iMethod == 'BANKDRAFT') {
              if (!$iAutID) {
                  $iAutID = 'draft';
              }
              $sGroupKey = MiscUtils::genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
          } elseif ($payment->iMethod == 'CREDITCARD') {
              if (!$iAutID) {
                  $iAutID = 'credit';
              }
              $sGroupKey = MiscUtils::genGroupKey($iAutID, $payment->FamilyID, $Fund->FundID, $payment->Date);
          } else {
              $sGroupKey = MiscUtils::genGroupKey('cash', $payment->FamilyID, $Fund->FundID, $payment->Date);
          }
        }
                $sSQL = "INSERT INTO pledge_plg
                    (plg_famID,
                    plg_FYID,
                    plg_date,
                    plg_amount,
                    plg_schedule,
                    plg_method,
                    plg_comment,
                    plg_DateLastEdited,
                    plg_EditedBy,
                    plg_PledgeOrPayment,
                    plg_fundID,
                    plg_depID,
                    plg_CheckNo,
                    plg_scanString,
                    plg_aut_ID,
                    plg_NonDeductible,
                    plg_GroupKey)
                    VALUES ('".
          $payment->FamilyID."','".
          $payment->FYID."','".
          $payment->Date."','".
          $Fund->Amount."','".
          (isset($payment->schedule) ? $payment->schedule : 'NULL')."','".
          $payment->iMethod."','".
          $Fund->Comment."','".
          date('YmdHis')."',".
          SessionUser::getUser()->getPersonId().",'".
          $payment->type."',".
          $Fund->FundID.','.
          $payment->DepositID.','.
          (isset($payment->iCheckNo) ? $payment->iCheckNo : 'NULL').",'".
          (isset($payment->tScanString) ? $payment->tScanString : 'NULL')."','".
          (isset($payment->iAutID) ? $payment->iAutID : 'NULL')."','".
          (isset($Fund->NonDeductible) ? $Fund->NonDeductible : 'NULL')."','".
          $sGroupKey."')";

                if (isset($sSQL)) {
                    MiscUtils::RunQuery($sSQL);
                    unset($sSQL);

                    return $sGroupKey;
                }
            }
        }
    }

    public function submitPledgeOrPayment($payment)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $this->validateFund($payment);
        $this->validateChecks($payment);
        $this->validateDate($payment);
        $groupKey = $this->insertPledgeorPayment($payment);

        return $this->getPledgeorPayment($groupKey);
    }

    public function getPledgeorPayment($GroupKey)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $total = 0;
        $sSQL = 'SELECT plg_plgID, plg_FamID, plg_date, plg_fundID, plg_amount, plg_NonDeductible,plg_comment, plg_FYID, plg_method, plg_EditedBy from pledge_plg where plg_GroupKey="'.$GroupKey.'"';
        $rsKeys = MiscUtils::RunQuery($sSQL);
        $payment = new \stdClass();
        $payment->funds = [];
        while ($aRow = mysqli_fetch_array($rsKeys)) {
            extract($aRow);
            $family = FamilyQuery::create()->findOneById($plg_FamID);
            $payment->Family = $family->getFamilyString();
            $payment->Date = $plg_date;
            $payment->FYID = $plg_FYID;
            $payment->iMethod = $plg_method;
            $fund['FundID'] = $plg_fundID;
            $fund['Amount'] = $plg_amount;
            $fund['NonDeductible'] = $plg_NonDeductible;
            $fund['Comment'] = $plg_comment;
            array_push($payment->funds, $fund);
            $total += $plg_amount;
            $onePlgID = $aRow['plg_plgID'];
            $oneFundID = $aRow['plg_fundID'];
            $iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
      $fund2PlgIds[$oneFundID] = $onePlgID;
        }
        $payment->total = $total;

        return json_encode($payment);
    }

    private function generateBankDepositSlip($thisReport)
    {
        // --------------------------------
    // BEGIN FRONT OF BANK DEPOSIT SLIP
    $thisReport->pdf->AddPage('L', [187, 84]);
        $thisReport->pdf->SetFont('Courier', '', 18);
    // Print Deposit Slip portion of report

    $thisReport->pdf->SetXY($thisReport->date1X, $thisReport->date1Y);
        $thisReport->pdf->Write(8, $thisReport->deposit->dep_Date);

        $thisReport->pdf->SetXY($thisReport->customerName1X, $thisReport->customerName1Y);
        $thisReport->pdf->Write(8, SystemConfig::getValue('sChurchName'));

        $thisReport->pdf->SetXY($thisReport->AccountNumberX, $thisReport->AccountNumberY);
        $thisReport->pdf->Cell(55, 7, SystemConfig::getValue('sChurchChkAcctNum'), 1, 1, 'R');

        if ($thisReport->deposit->totalCash > 0) {
            $totalCashStr = sprintf('%.2f', $thisReport->deposit->totalCash);
            $thisReport->pdf->SetXY($thisReport->cashX, $thisReport->cashY);
            $thisReport->pdf->Cell(46, 7, $totalCashStr, 1, 1, 'R');
        }

        if ($thisReport->deposit->totalChecks > 0) {
            $totalChecksStr = sprintf('%.2f', $thisReport->deposit->totalChecks);
            $thisReport->pdf->SetXY($thisReport->checksX, $thisReport->checksY);
            $thisReport->pdf->Cell(46, 7, $totalChecksStr, 1, 1, 'R');
        }

        $grandTotalStr = sprintf('%.2f', $thisReport->deposit->dep_Total);
        $cashReceivedStr = sprintf('%.2f', 0);

        $thisReport->pdf->SetXY($thisReport->cashReceivedX, $thisReport->cashReceivedY);
        $thisReport->pdf->Cell(46, 7, $cashReceivedStr, 1, 1, 'R');

        $thisReport->pdf->SetXY($thisReport->topTotalX, $thisReport->topTotalY);
        $thisReport->pdf->Cell(46, 7, $grandTotalStr, 1, 1, 'R');

    // --------------------------------
    // BEGIN BACK OF BANK DEPOSIT SLIP

    $thisReport->pdf->AddPage('P', [84, 187]);
        $numItems = 0;
        foreach ($thisReport->payments as $payment) {
            // List all the checks and total the cash
      if ($payment->plg_method == 'CHECK') {
          $plgSumStr = sprintf('%.2f', $payment->plg_amount);
          $thisReport->pdf->SetFontSize(14);
          $thisReport->pdf->SetXY($thisReport->depositSlipBackCheckNosX, $thisReport->depositSlipBackCheckNosY + $numItems * $thisReport->depositSlipBackCheckNosHeight);
          $thisReport->pdf->Cell($thisReport->depositSlipBackCheckNosWidth, $thisReport->depositSlipBackCheckNosHeight, $payment->plg_CheckNo, 1, 0, 'L');
          $thisReport->pdf->SetFontSize(18);
          $thisReport->pdf->Cell($thisReport->depositSlipBackDollarsWidth, $thisReport->depositSlipBackDollarsHeight, $plgSumStr, 1, 1, 'R');
          $numItems += 1;
      }
        }
    }

    private function generateDepositSummary($thisReport)
    {
        $thisReport->depositSummaryParameters->title->x = 85;
        $thisReport->depositSummaryParameters->title->y = 7;
        $thisReport->depositSummaryParameters->date->x = 185;
        $thisReport->depositSummaryParameters->date->y = 7;
        $thisReport->depositSummaryParameters->summary->x = 12;
        $thisReport->depositSummaryParameters->summary->y = 15;
        $thisReport->depositSummaryParameters->summary->intervalY = 4;
        $thisReport->depositSummaryParameters->summary->FundX = 15;
        $thisReport->depositSummaryParameters->summary->MethodX = 55;
        $thisReport->depositSummaryParameters->summary->FromX = 80;
        $thisReport->depositSummaryParameters->summary->MemoX = 120;
        $thisReport->depositSummaryParameters->summary->AmountX = 185;
        $thisReport->depositSummaryParameters->aggregateX = 135;
        $thisReport->depositSummaryParameters->displayBillCounts = false;

        $thisReport->pdf->AddPage();
        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->date->x, $thisReport->depositSummaryParameters->date->y);
        $thisReport->pdf->Write(8, $thisReport->deposit->dep_Date);

        $thisReport->pdf->SetXY($thisReport->depositSummaryParameters->title->x, $thisReport->depositSummaryParameters->title->y);
        $thisReport->pdf->SetFont('Courier', 'B', 20);
        $thisReport->pdf->Write(8, 'Deposit Summary '.$thisReport->deposit->dep_ID);
        $thisReport->pdf->SetFont('Times', 'B', 10);

        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;
        $thisReport->curY = $thisReport->depositSummaryParameters->summary->y;

        $thisReport->pdf->SetFont('Times', 'B', 10);
        $thisReport->pdf->SetXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Chk No.');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FundX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Fund');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MethodX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'PmtMethod');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FromX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Rcd From');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Memo');

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Amount');
        $thisReport->curY += 2 * $thisReport->depositSummaryParameters->summary->intervalY;

        $totalAmount = 0;

    //while ($aRow = mysqli_fetch_array($rsPledges))
    foreach ($thisReport->payments as $payment) {
        $thisReport->pdf->SetFont('Times', '', 10);

      // Format Data
      if (strlen($payment->plg_CheckNo) > 8) {
          $payment->plg_CheckNo = '...'.mb_substr($payment->plg_CheckNo, -8, 8);
      }
        if (strlen($payment->fun_Name) > 20) {
            $payment->fun_Name = mb_substr($payment->fun_Name, 0, 20).'...';
        }
        if (strlen($payment->plg_comment) > 40) {
            $payment->plg_comment = mb_substr($payment->plg_comment, 0, 38).'...';
        }
        if (strlen($payment->familyName) > 25) {
            $payment->familyName = mb_substr($payment->familyName, 0, 24).'...';
        }

        $thisReport->pdf->PrintRightJustified($thisReport->curX + 2, $thisReport->curY, $payment->plg_CheckNo);

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FundX, $thisReport->curY);
        $thisReport->pdf->Write(8, $payment->fun_Name);

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MethodX, $thisReport->curY);
        $thisReport->pdf->Write(8, $payment->plg_method);

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->FromX, $thisReport->curY);
        $thisReport->pdf->Write(8, $payment->familyName);

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, $payment->plg_comment);

        $thisReport->pdf->SetFont('Courier', '', 8);

        $thisReport->pdf->PrintRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $payment->plg_amount);

        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

        if ($thisReport->curY >= 250) {
            $thisReport->pdf->AddPage();
            $thisReport->curY = $thisReport->topY;
        }
    }

        $thisReport->curY += $thisReport->depositSummaryParameters->summary->intervalY;

        $thisReport->pdf->SetXY($thisReport->curX + $thisReport->depositSummaryParameters->summary->MemoX, $thisReport->curY);
        $thisReport->pdf->Write(8, 'Deposit total');

        $grandTotalStr = sprintf('%.2f', $thisReport->deposit->dep_Total);
        $thisReport->pdf->PrintRightJustified($thisReport->curX + $thisReport->depositSummaryParameters->summary->AmountX, $thisReport->curY, $grandTotalStr);

    // Now print deposit totals by fund
    $thisReport->curY += 2 * $thisReport->depositSummaryParameters->summary->intervalY;
        if ($thisReport->depositSummaryParameters->displayBillCounts) {
            $this->generateCashDenominations($thisReport);
        }
        $thisReport->curX = $thisReport->depositSummaryParameters->aggregateX;
        $this->generateTotalsByFund($thisReport);

        $thisReport->curY += $thisReport->summaryIntervalY;
        $this->generateTotalsByCurrencyType($thisReport);
        $thisReport->curY += $thisReport->summaryIntervalY * 2;

        $thisReport->curY += 130;
        $thisReport->curX = $thisReport->depositSummaryParameters->summary->x;

        $this->generateWitnessSignature($thisReport);
    }

    private function generateWitnessSignature($thisReport)
    {
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 1');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 2');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);

        $thisReport->curY += 10;
        $thisReport->pdf->setXY($thisReport->curX, $thisReport->curY);
        $thisReport->pdf->write(8, 'Witness 3');
        $thisReport->pdf->line($thisReport->curX + 17, $thisReport->curY + 8, $thisReport->curX + 80, $thisReport->curY + 8);
    }

    public function getDepositPDF($depID)
    {
    }

    public function getDepositCSV($depID)
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $retstring = '';
        $line = [];
        $firstLine = true;
        $payments = $this->getPayments($depID);
        if (count($payments) == 0) {
            throw new \Exception('No Payments on this Deposit', 404);
        }
        foreach ($payments[0] as $key => $value) {
            array_push($line, $key);
        }
        $retstring = implode(',', $line)."\n";
        $line = [];
        foreach ($payments as $payment) {
            $line = [];
            foreach ($payment as $key => $value) {
                array_push($line, str_replace(',', '', $value));
            }
            $retstring .= implode(',', $line)."\n";
        }

        $CSVReturn = new \stdClass();
        $CSVReturn->content = $retstring;
    // Export file
    $CSVReturn->header = 'Content-Disposition: attachment; filename=EcclesiaCRM-DepositCSV-'.$depID.'-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv';

        return $CSVReturn;
    }

    public function getCurrencyTypeOnDeposit($currencyID, $depositID)
    {
        $currencies = [];
        // Get the list of Currency denominations
        $sSQL = 'select sum(pdem_denominationQuantity) from pledge_denominations_pdem
                 where  plg_depID = '.$depositID.'
                 AND
                 pdem_denominationID = '.$currencyID;
        $rscurrencyDenomination = MiscUtils::RunQuery($sSQL);

        return mysqli_fetch_array($rscurrencyDenomination)[0];
    }

    public function getCurrency()
    {
        $currencies = [];
    // Get the list of Currency denominations
    $sSQL = 'SELECT * FROM currency_denominations_cdem';
        $rscurrencyDenomination = MiscUtils::RunQuery($sSQL);
        mysqli_data_seek($rscurrencyDenomination, 0);
        while ($row = mysqli_fetch_array($rscurrencyDenomination)) {
            $currency = new \stdClass();
            $currency->id = $row['cdem_denominationID'];
            $currency->Name = $row['cdem_denominationName'];
            $currency->Value = $row['cdem_denominationValue'];
            $currency->cClass = $row['cdem_denominationClass'];
            array_push($currencies, $currency);
        } // end while
    return $currencies;
    }

    public function getActiveFunds()
    {
        MiscUtils::requireUserGroupMembership('bFinance');
        $funds = [];
        $sSQL = 'SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun';
        $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
    $rsFunds = MiscUtils::RunQuery($sSQL);
        mysqli_data_seek($rsFunds, 0);
        while ($aRow = mysqli_fetch_array($rsFunds)) {
            $fund = new \stdClass();
            $fund->ID = $aRow['fun_ID'];
            $fund->Name = $aRow['fun_Name'];
            $fund->Description = $aRow['fun_Description'];
            array_push($funds, $fund);
        } // end while
    return $funds;
    }
}
