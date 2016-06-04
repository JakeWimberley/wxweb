<!DOCTYPE html>
<!-- vim: set expandtab sw=2 ts=2: -->
<?php
  date_default_timezone_set('UTC');
  $bTestMode = FALSE;
  if ($bTestMode) {
    ini_set('display_errors',TRUE);
    error_reporting(E_ALL);
    //$_GET['sid'] = 'KORD';
  } else {
    ini_set('display_errors',FALSE);
  }
  $disclaimer = "TAF and METAR data courtesy <a href=\"http://aviationweather.gov/adds/\">Aviation Weather Center</a>, <a href=\"http://weather.gov\">National Weather Service</a>.<br><b>WARNING! DO NOT</b> use these data for flight planning. The data presented here are for informational and educational use only. Reliability cannot be guaranteed; use at your own risk.";
  $self = 'taf.php'; //TODO shouldn't need this
  $catNames = array('vlifr','lifr','ifr1','ifr2','mvfr1','mvfr2','vfr1','vfr2','vfr3');
  $catNames2 = array('VLIFR','LIFR','IFR','IFR','MVFR','MVFR','VFR','VFR','VFR');
  $catCigMin = array(-1,200,500,500,1000,2000,3000,6500,12000);
  $catVisMin = array(-1,0.5,1.0,2.0,3.0,3.0,6.0,7.0,7.0);
  $mySid = '';
?>
<html>
<head>
	<meta charset="utf-8">
	<title>Dr. Archibald TAF, M.D.</title>
	<link href="drtaf.css" rel="stylesheet">
  <script language="javascript">
function ShowGroup(n) {
  var displayRule;
  if (n == -1) displayRule = 'block';
  else displayRule = 'none';
    var metarTableList = document.querySelectorAll('table.metar');
    for (var i = 0; i < metarTableList.length; i++)
      metarTableList[i].style.display = displayRule;
  if (n == -1) return;
  document.getElementById('metarGroup'+n).style.display = 'block';
}
  </script>
</head>
<body>
  <p style="font-size: 20px; margin: 10px 0px 10px 0px;"><span style="font-size: 36px;">D<span style="font-family: serif; font-size: 28px;">&#8478;</span>. GoodTAF</span> or: <i>How I Learned To Stop Worrying and Love VLIFR</i></p>
<?php
  $tafSearchStartTime = preg_replace('/\+00:00/','Z',date('c',strtotime('31 hours ago')));
  $tafSearchEndTime = preg_replace('/\+00:00/','Z',date('c',strtotime('25 hours ago')));
  if (isset($_GET['sid'])) {
    $mySid = strtoupper(substr($_GET['sid'],0,4));
    if (strlen($mySid) == 3) $mySid = "K$mySid";

    // Fetch TAF and obs from valid period
    $addsTafUrl = "http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=tafs&requestType=retrieve&format=xml&startTime=$tafSearchStartTime&endTime=$tafSearchEndTime&timeType=issue&mostRecent=true&stationString=$mySid";
    if ($bTestMode) echo "<a href=\"$addsTafUrl\">TAF XML</a><br>\n";
    $tafXml = simplexml_load_file($addsTafUrl);
    $tafs = [];
    foreach ($tafXml->data->TAF as $issuanceXml) {
      $taf = $issuanceXml->raw_text;
      $issueTime = $issuanceXml->issue_time;
      $validStart = $issuanceXml->valid_time_from;
      $validEnd = $issuanceXml->valid_time_to;
      $addsMetarUrl = "http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=metars&requestType=retrieve&format=xml&startTime=$validStart&endTime=$validEnd&stationString=$mySid";
      if ($bTestMode) echo "<a href=\"$addsMetarUrl\">Obs XML</a><br>\n";
      $obsXml = simplexml_load_file($addsMetarUrl);
      // We can't rely on the TAF periods in the XML to be in the same order as
      // they were in the actual TAF. So we're going to step thru the periods
      // and put them into an array, which can then be sorted.
      $forecastPeriods = [];
      foreach ($issuanceXml->forecast as $periodXml) {
        $begin = date_create($periodXml->fcst_time_from);
        $end = date_create($periodXml->fcst_time_to);
        array_push($forecastPeriods,$periodXml);
      }
      usort($forecastPeriods, 'sortPeriods');
      // Bin obs into the ordered TAF group periods.
      // While we've got the period object, we'll also determine the category.
      $obsBinnedByPeriod = [];
      $categories = [];
      $iPeriod = 0;
      foreach ($forecastPeriods as $periodXml) {
        $begin = date_create($periodXml->fcst_time_from);
        $end = date_create($periodXml->fcst_time_to);
        array_push($categories, CategoryOfGroup($periodXml));
        array_push($obsBinnedByPeriod, []);
        foreach ($obsXml->data->METAR as $obXml) {
          $obTime = date_create($obXml->observation_time);
          if ($obTime > $begin && $obTime <= $end) {
            array_unshift($obsBinnedByPeriod[$iPeriod],(string)$obXml->raw_text);
            //echo "<b>begin=".date_format($begin,'c')."</b>\n";
            //echo "<b>".date_format($obTime,'c')."</b>\n";
            //echo "<b>end=".date_format($end,'c')."</b><br>\n";
          }
        }
        $iPeriod++;
      }
      // print TAFs and obs
      //// TAF DISPLAY SECTION
      echo "<div class=\"left\">\n";
      echo "<p id=\"tafTitle\" onclick=\"javascript:ShowGroup(-1)\">TAF (click group to show only obs from that period, click here to show all)</p>\n";
      echo "<table class=\"left\">\n";
      list($fm,$cond) = SplitTaf($taf);
      list($fmTime,$condTime) = TafTimes($fm,$cond);
      // groupNum is handled separately since the block arrays make
      // no distinction between FM and conditional groups
      for ($i = 0, $groupNum = 0; $i < count($fm); $i++, $groupNum++) {
        list($cat,$catName) = $categories[$groupNum];
        echo "  <tr onclick=\"javascript:ShowGroup($groupNum)\">\n";
        if ($bTestMode) {
          $timeLimits = $fmTime[$i][0][0] . $fmTime[$i][0][1] . '/' .
                        $fmTime[$i][1][0] . $fmTime[$i][1][1];
          echo "    <td>" . $groupNum . "<br><small>$timeLimits</small></td>\n";
        } else {
          echo "    <td>" . $groupNum . "</td>\n";
        }
        echo "    <td class=\"catname$cat\">$catName</td>\n";
        echo "    <td class=\"tafmetar$cat\">";
        if (preg_match('/^FM/',$fm[$i])) echo str_repeat('&nbsp;',5);
        echo $fm[$i]."</td>\n";
        echo "  </tr>\n";
        if (! empty($cond[$i])) {
          $groupNum++;
          list($cat,$catName) = $categories[$groupNum];
          echo "  <tr onclick=\"javascript:ShowGroup($groupNum)\">\n";
          if ($bTestMode) {
            $timeLimits = $condTime[$i][0][0] . $condTime[$i][0][1] . '/' .
                          $condTime[$i][1][0] . $condTime[$i][1][1];
            echo "    <td>" . $groupNum . "<br><small>$timeLimits</small></td>\n";
          } else {
            echo "    <td>" . $groupNum . "</td>\n";
          }
          echo "    <td class=\"catname$cat\">$catName</td>\n";
          echo "    <td class=\"tafmetar$cat\">".str_repeat('&nbsp;',6).$cond[$i]."</td>\n";
          echo "  </tr>\n";
        }
      }
      echo "</table>\n";
      PrintForm();
      echo "</div>\n";
      echo "<div class=\"right\">\n";
      //// METAR DISPLAY SECTION
      $obGroup = 0;
      echo "<p id=\"metarTitle\">Observations during the TAF valid period</p>\n";
      foreach ($obsBinnedByPeriod as $periodObs) {
        echo "<table class=\"metar right\" id=\"metarGroup$obGroup\">\n";
        foreach ($periodObs as $metar) {
          $metar = str_replace("\n",' ',$metar);
          list($cat,$catName) = CategoryFromCoded($metar);
          echo "  <tr>\n";
          echo "    <td>$obGroup</td>\n";
          echo "    <td class=\"catname$cat\">$catName</td>\n";
          echo "    <td class=\"tafmetar$cat\">$metar</td>\n";
          echo "  </tr>\n";
        }
        echo "</table>\n";
        $obGroup++;
      }
      echo "</div>\n";
    }
  } else { // ! isset sid
?>
  <p>This is a TAF verification tool. Enter a TAF site, and it will be displayed along with the obs from its valid period.</p>
<?php
    PrintForm();
  }
?>
  </body>
</html>
<?php
// Determine the start and end times of TAF groups.
function TafTimes($fms,$conds) {
  global $fm, $cond;
  $pds = array(); // $pds[0][0] = start of first group, $pds[2][1] = end of third group, etc.
  $pdsC = array(); // same format; only assigned value if a conditional group exists for the matching FM group
  $fMax = count($fms) - 1;
  for ($f = 0; $f <= $fMax; $f++) {
    if ($f == 0) {
      if (preg_match('| (\d\d)(\d\d)/(\d\d)(\d\d) |',$fm[$f],$_times)) {
        list($startDay,$startHour,$endDay,$endHour) = array_slice($_times,1,4);
        $startMinute = 0;
        $endMinute = 0;
        // set start of first period, then end of last period
        list($pds[$f][0][0],$pds[$f][0][1],$pds[$f][0][2]) = array($startDay,$startHour,$startMinute);
        list($pds[$fMax][1][0],$pds[$fMax][1][1],$pds[$fMax][1][2]) = array($endDay,$endHour,$endMinute);
      } // TODO a failure mode
    } else {
      if (preg_match('/FM(\d\d)(\d\d)(\d\d) /',$fm[$f],$_times)) {
        // set end of previous period to start of this one
        // then set start of this period
        list($pds[$f-1][1][0],$pds[$f-1][1][1],$pds[$f-1][1][2]) = array_slice($_times,1,3);
        list($pds[$f][0][0],$pds[$f][0][1],$pds[$f][0][2]) = array_slice($_times,1,3);
      }
    }
    if (strlen($cond[$f]) > 0) {
      if (preg_match('| (\d\d)(\d\d)/(\d\d)(\d\d) |',$cond[$f],$_times)) {
        list($startDay,$startHour,$endDay,$endHour) = array_slice($_times,1,4);
        $startMinute = 0;
        $endMinute = 0;
        list($pdsC[$f][0][0],$pdsC[$f][0][1],$pdsC[$f][0][2]) = array($startDay,$startHour,$startMinute);
        list($pdsC[$f][1][0],$pdsC[$f][1][1],$pdsC[$f][1][2]) = array($endDay,$endHour,$endMinute);
      } // TODO a failure mode
    } else {
      $pdsC[$f] = array();
    }
  }
  return array($pds,$pdsC);
}

// Get category of a TAF group or METAR based on the coded wx data therein.
function CategoryFromCoded($group) {
  global $catNames, $catNames2, $catCigMin, $catVisMin, $bTestMode;
  $catCig = count($catNames)-1; // default to VFR if not defined in group
  if (preg_match('/(?:BKN|OVC|VV)(\d{3})/',$group,$_cig)) {
    $_cig[1] = ltrim($_cig[1],'0');
    for ($i = 1; $i < count($catCigMin); $i++) {
      if ($_cig[1] * 100 < $catCigMin[$i]) {
        $catCig = $i-1;
        break;
      }
    }
  } else {
    $_cig[1] = -1;
  }
  $vis = 99; // default to VFR if not defined in group or is P6SM
  $catVis = count($catNames)-1;
  $vmatch = preg_match('!([MP]?(?:\d{1,2}|(?:\d )?\d{1,2}/\d{1,2}))SM!',$group,$_vis);
  if ($vmatch > 0) {
    if ($_vis[1] == 'M1/4') {
      $catVis = 0;
    } else if ($_vis[1] != 'P6') {
      $vis = $_vis[1];
      $fracPat = '|(\d+)/(\d+)|';
      if (preg_match($fracPat,$_vis[1],$frac)) { // find fraction, if any
        $_vis[1] = preg_replace($fracPat,'',$_vis[1]);
        $vis = $_vis[1] + $frac[1] / $frac[2];
      }
      for ($i = 1; $i < count($catVisMin); $i++) {
        if ($vis < $catVisMin[$i]) {
          $catVis = $i-1;
          break;
        }
      }
    }
  }
  if ($bTestMode)
  return array(' '.$catNames[min($catCig,$catVis)],
               'cig: '.$catNames2[$catCig]." ($_cig[1]00 ft) vis: ".$catNames2[$catVis]." ($vis mi) ---> ".$catNames2[min($catCig,$catVis)]);
  else return array(' '.$catNames[min($catCig,$catVis)],$catNames2[min($catCig,$catVis)]);
}

// Get category of a TAF period based on the met data in XML fields.
function CategoryOfGroup($xmlObj) {
  global $catNames, $catNames2, $catCigMin, $catVisMin, $bTestMode;
  $catCig = count($catNames)-1; // default to VFR if not defined in group
  // Find the cig in this group.
  $lowestCig = 99999;
  $cloudBaseHeight = 99999;
  foreach ($xmlObj->sky_condition as $sky) {
    if (preg_match('/(?:BKN|OVC|VV)/',(string)$sky['sky_cover'])) {
      $cloudBaseHeight = (int)$sky['cloud_base_ft_agl'];
      if ($cloudBaseHeight < $lowestCig) $lowestCig = $cloudBaseHeight;
    }
  }
  // Find which category that cig falls into.
  for ($i = 1; $i < count($catCigMin); $i++) {
    if ($lowestCig < $catCigMin[$i]) {
      $catCig = $i-1;
      break;
    }
  }
  // Vsby easier since it is a single value.
  // However it might be omitted, hence property_exists() call
  $vis = 6.21;
  if (property_exists($xmlObj,'visibility_statute_mi'))
    $vis = (int)$xmlObj->visibility_statute_mi;
  for ($i = 1; $i < count($catVisMin); $i++) {
    if ($vis < $catVisMin[$i]) {
      $catVis = $i-1;
      break;
    }
  }
  if ($bTestMode)
  return array(' '.$catNames[min($catCig,$catVis)],
               'cig: '.$catNames2[$catCig]." ($cloudBaseHeight ft) vis: ".$catNames2[$catVis]." ($vis mi) ---> ".$catNames2[min($catCig,$catVis)]);
  else return array(' '.$catNames[min($catCig,$catVis)],$catNames2[min($catCig,$catVis)]);
}

// Get arrays of FM and conditional groups based on a TAF string.
// Input and output are both in coded format.
function SplitTaf($rawStr) {
  global $mySid;
  $tafStr = preg_replace("/\s+/",' ',$rawStr);
//  echo "<pre>$tafStr</pre>\n";
  $fmOffset = 0;
  $fm = array();
  while ($fmOffset < strlen($tafStr)) {
    $fmMatch = preg_match("/(?:$mySid|FM\d{6}).+?(?:FM\d{6}|=|$)/",$tafStr,$_fm,PREG_OFFSET_CAPTURE,$fmOffset);
    if ($fmMatch < 1) break;
    array_push($fm,preg_replace("/\s*(?:FM\d{6})?$/",'',$_fm[0][0]));
    $fmOffset = $_fm[0][1] + strlen($_fm[0][0]) - 8; // 8 is len of FMdddddd
  }
//  $fmStr = implode("\n",$fm);
//  echo "<pre>$fmStr</pre>\n";
  $cond = array();
  for ($i = 0; $i < count($fm); $i++) {
    // TODO support BECMG?
    $condPat = '/((?:TEMPO|PROB).+$)/';
    if (preg_match($condPat,$fm[$i],$_cond)) {
      $cond[$i] = $_cond[1];
      $fm[$i] = preg_replace($condPat,'',$fm[$i]);
    } else {
      $cond[$i] = '';
    }
  }
  return([$fm,$cond]);
}

// Custom function for asort() to put the TAF period XML blocks in
// ascending chronological order.
function sortPeriods($a,$b) {
      $beginA = date_create($a->fcst_time_from);
      $beginB = date_create($b->fcst_time_from);
      if (property_exists($a,'change_indicator'))
        $groupTypeA = (string)$a->change_indicator;
      else
        $groupTypeA = '';
      if (property_exists($b,'change_indicator'))
        $groupTypeB = (string)$b->change_indicator;
      else
        $groupTypeB = '';
      if ($beginA < $beginB) return -1;
      else if ($beginA > $beginB) return 1;
      // if start times are equal, the FM group is first
      else if ($groupTypeA === 'FM' || empty($groupTypeA)) return -1;
      else if ($groupTypeB === 'FM' || empty($groupTypeB)) return 1;
      else return 0; // should never get here
}

function PrintForm() {
  global $mySid, $disclaimer;
  $value = (strlen($mySid) > 0) ? ' value="'.$mySid.'"' : '';
?>
  <form action="<?php echo $self?>" method="get">
    <p>
      <label for="sid">Airport ICAO ID <i>(e.g. KJFK)</i></label>
      <span style="padding-left: 20px">
      <input type="text" name="sid" size="5" maxlength="4"<?php echo $value; ?>>
      </span>
    </p>
    <input type="submit" value="Go">
  </form>
<?php
  echo "<p class=\"disclaimer\">$disclaimer</p>\n";
}
?>
