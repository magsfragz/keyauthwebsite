<?php
if ($_SESSION['role'] == "Reseller") {
    header("location: ./?page=reseller-licenses");
	die();
}
if($role == "Manager" && !($permissions & 1)) {
	die('You weren\'t granted permissions to view this page.');
}
if(!isset($_SESSION['app'])) {
	die("Application not selected.");
}
if (isset($_POST['deletekey'])) {
    $userToo = ($_POST['delUserToo'] == "on") ? 1 : 0;
    $resp = misc\license\deleteSingular($_POST['deletekey'], $userToo);
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Failed to delete license!");
            break;
        case 'success':
            dashboard\primary\success("Successfully deleted license!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['bankey'])) {
    $userToo = ($_POST['banUserToo'] == "on") ? 1 : 0;
    $resp = misc\license\ban($_POST['bankey'], $_POST['reason'], $userToo);
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Failed to ban license!");
            break;
        case 'success':
            dashboard\primary\success("Successfully banned license!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['unbankey'])) {
    $resp = misc\license\unban($_POST['unbankey']);
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Failed to unban license!");
            break;
        case 'success':
            dashboard\primary\success("Successfully unbanned license!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['editkey'])) {
    $key = misc\etc\sanitize($_POST['editkey']);
    $result = mysqli_query($link, "SELECT * FROM `keys` WHERE `key` = '$key' AND `app` = '" . $_SESSION['app'] . "'");
    if (mysqli_num_rows($result) < 1) {
        mysqli_close($link);
        error("Key not Found!");
        echo "<meta http-equiv='Refresh' Content='2'>";
        return;
    }
    $row = mysqli_fetch_array($result);
?>
<div id="edit-key" class="modal show" role="dialog" aria-labelledby="myModalLabel" style="display: block;"
    aria-modal="true">

    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title">Edit License</h4>
                <!--begin::Close-->
                <div class="btn btn-sm btn-icon btn-active-color-primary"
                    onClick="window.location.href=window.location.href">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                fill="black" />
                        </svg>
                    </span>
                </div>
                <!--end::Close-->
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="form-group">
                        <label for="recipient-name" class="control-label">Key Level:</label>
                        <input type="text" class="form-control" name="level" value="<?php echo $row['level']; ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="recipient-name" class="control-label">License Duration Unit:</label>
                        <select name="expiry" class="form-control">
                            <option value="86400">Days</option>
                            <option value="60">Minutes</option>
                            <option value="3600">Hours</option>
                            <option value="1">Seconds</option>
                            <option value="604800">Weeks</option>
                            <option value="2629743">Months</option>
                            <option value="31556926">Years</option>
                            <option value="315569260">Lifetime</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="recipient-name" class="control-label">License Duration: <i
                                class="fas fa-question-circle fa-lg text-white-50" data-toggle="tooltip"
                                data-placement="top"
                                title="Editing license duration after the license has been used will do nothing. Used licenses become users so you need to go to users tab and click extend user(s) instead"></i></label>
                        <input name="duration" type="number" class="form-control"
                            placeholder="Multiplied by selected Expiry unit">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" onClick="window.location.href=window.location.href" class="btn btn-secondary"
                    data-dismiss="modal">Close</button>
                <button class="btn btn-danger" name="savekey" value="<?php echo $key; ?>">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
}
if (isset($_POST['savekey'])) {
    $key = misc\etc\sanitize($_POST['savekey']);
    $level = misc\etc\sanitize($_POST['level']);
    $duration = misc\etc\sanitize($_POST['duration']);
    if (!empty($duration)) {
        $expiry = misc\etc\sanitize($_POST['expiry']);
        $duration = $duration * $expiry;
        mysqli_query($link, "UPDATE `keys` SET `expires` = '$duration' WHERE `key` = '$key' AND `app` = '" . $_SESSION['app'] . "'");
    }
    mysqli_query($link, "UPDATE `keys` SET `level` = '$level' WHERE `key` = '$key' AND `app` = '" . $_SESSION['app'] . "'");
    dashboard\primary\success("Successfully Updated Settings!");
}

if (isset($_POST['importkeys'])) {
	
	if(!empty($_POST['authgg'])) {
		$json = $_POST['authgg'];
		$data = json_decode($json);

		$levels = array();
		foreach($data as $key => $row) {
			$license = misc\etc\sanitize($row->token);
			$level = misc\etc\sanitize($row->rank) + 1;
			$usedby = misc\etc\sanitize($row->used_by);
			$expires = misc\etc\sanitize($row->days) * 86400; // convert num of days to unix
			$status = "Not Used";
			if(!empty($usedby)) {
				$status = "Used";
			}
			else {
				if(!in_array($level, $levels)) {
					mysqli_query($link, "INSERT INTO `subscriptions`(`name`, `level`, `app`) VALUES ('rank  ".$level."','$level', '" . $_SESSION['app'] . "')");
					$levels[] = $level; // prevent doing an insert statement for the same level twice
				}
				$usedby = NULL;
			}
			
			mysqli_query($link, "INSERT INTO `keys`(`key`, `expires`, `status`, `level`, `genby`, `gendate`, `usedby`, `app`) VALUES ('$license','$expires', '$status', '$level', '" . $_SESSION['username'] . "', UNIX_TIMESTAMP(), NULLIF('$usedby', ''), '" . $_SESSION['app'] . "')");
		}
	}
	else {
		$keys = misc\etc\sanitize($_POST['keys']);
		$text = explode("|", $keys);
		str_replace('"', "", $text);
		str_replace("'", "", $text);
		foreach ($text as $line) {
			$array = explode(',', $line);
			$first = $array[0];
			if (!isset($first) || $first == '') {
				dashboard\primary\error("Invalid Format, please watch tutorial video!");
				echo "<meta http-equiv='Refresh' Content='2;'>";
				return;
			}
			$second = $array[1];
			if (!isset($second) || $second == '') {
				dashboard\primary\error("Invalid Format, please watch tutorial video!");
				echo "<meta http-equiv='Refresh' Content='2;'>";
				return;
			}
			$third = $array[2];
			if (!isset($third) || $third == '') {
				dashboard\primary\error("Invalid Format, please watch tutorial video!");
				echo "<meta http-equiv='Refresh' Content='2;'>";
				return;
			}
			$expiry = $third * 86400;
			mysqli_query($link, "INSERT INTO `keys` (`key`, `expires`, `status`, `level`, `genby`, `gendate`, `app`) VALUES ('$first','$expiry','Not Used','$second','" . $_SESSION['username'] . "','" . time() . "','" . $_SESSION['app'] . "')");
		}
	}
    dashboard\primary\success("Successfully imported licenses!");
}
if (isset($_POST['addtime'])) {
    $resp = misc\license\addTime($_POST['time'], $_POST['expiry']);
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Failed to add time!");
            break;
        case 'success':
            dashboard\primary\success("Added time to unused licenses!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['dlkeys'])) {
    echo "<meta http-equiv='Refresh' Content='0; url=license-download.php'>";
    // get all rows, put in text file, download text file, delete text file.

}
if (isset($_POST['delkeys'])) {
    $resp = misc\license\deleteAll();
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Didn\'t find any keys!");
            break;
        case 'success':
            dashboard\primary\success("Deleted All Keys!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['deleteallunused'])) {
    $resp = misc\license\deleteAllUnused();
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Didn\'t find any unused keys!");
            break;
        case 'success':
            dashboard\primary\success("Deleted All Unused Keys!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}
if (isset($_POST['deleteallused'])) {
    $resp = misc\license\deleteAllUsed();
    switch ($resp) {
        case 'failure':
            dashboard\primary\error("Didn\'t find any used keys!");
            break;
        case 'success':
            dashboard\primary\success("Deleted All Used Keys!");
            break;
        default:
            dashboard\primary\error("Unhandled Error! Contact us if you need help");
            break;
    }
}

if (isset($_POST['genkeys'])) {
    $key = misc\license\createLicense($_POST['amount'], $_POST['mask'], $_POST['duration'], $_POST['level'], $_POST['note'], $_POST['expiry']);
    switch ($key) {
        case 'max_keys':
            dashboard\primary\error("You can only generate 100 licenses at a time");
            break;
        case 'tester_limit':
            dashboard\primary\error("Tester plan only allows for 50 licenses, please upgrade!");
            break;
        case 'dupe_custom_key':
            dashboard\primary\error("Can\'t do custom key with amount greater than one");
            break;
        default:
            mysqli_query($link, "UPDATE `apps` SET `format` = '" . misc\etc\sanitize($_POST['mask']) . "',`amount` = '" . misc\etc\sanitize($_POST['amount']) . "',`lvl` = '" . misc\etc\sanitize($_POST['level']) . "',`note` = '" . misc\etc\sanitize($_POST['note']) . "',`duration` = '" . misc\etc\sanitize($_POST['duration']) . "',`unit` = '" . misc\etc\sanitize($_POST['expiry']) . "' WHERE `secret` = '" . $_SESSION['app'] . "'");
            if (misc\etc\sanitize($_POST['amount']) > 1) {
                $_SESSION['keys_array'] = $key;
            } else {
                echo "<script>navigator.clipboard.writeText('" . array_values($key)[0] . "');</script>";
                dashboard\primary\success("License Created And Copied To Clipboard!");
            }
            break;
    }
}

// error_reporting(1);                          
?>
<!--begin::Container-->
<div id="kt_content_container" class="container-xxl">
    <script src="https://cdn.keyauth.cc/dashboard/unixtolocal.js"></script>
    <form method="POST">
        <button type="button" data-bs-toggle="modal" data-bs-target="#create-keys"
            class="dt-button buttons-print btn btn-primary mr-1"><i class="fas fa-plus-circle fa-sm text-white-50"></i>
            Create keys</button>
        <button data-bs-toggle="modal" type="button" data-bs-target="#import-keys"
            class="dt-button buttons-print btn btn-primary mr-1"><i
                class="fas fa-cloud-upload-alt fa-sm text-white-50"></i> Import keys</button>
        <button data-bs-toggle="modal" type="button" data-bs-target="#comp-keys"
            class="dt-button buttons-print btn btn-primary mr-1"><i class="fas fa-clock fa-sm text-white-50"></i> Add
            Time</button><br><br>
        <button name="dlkeys" class="dt-button buttons-print btn btn-primary mr-1"><i
                class="fas fa-download fa-sm text-white-50"></i> Download All keys</button>
        <button type="button" data-bs-toggle="modal" data-bs-target="#delete-allkeys"
            class="dt-button buttons-print btn btn-primary mr-1"><i class="fas fa-trash-alt fa-sm text-white-50"></i>
            Delete All keys</button>
        <button type="button" data-bs-toggle="modal" data-bs-target="#delete-allunusedkeys"
            class="dt-button buttons-print btn btn-primary mr-1"><i class="fas fa-trash-alt fa-sm text-white-50"></i>
            Delete All Unused Keys</button>
        <button type="button" data-bs-toggle="modal" data-bs-target="#delete-allusedkeys"
            class="dt-button buttons-print btn btn-primary mr-1"><i class="fas fa-trash-alt fa-sm text-white-50"></i>
            Delete All Used Keys</button>

    </form>
    <br>
    <?php
    if (isset($_SESSION['keys_array'])) {
        $list = $_SESSION['keys_array'];
        $keys = NULL;
        for ($i = 0; $i < count($list); $i++) {
            $keys .= "" . $list[$i] . "<br>";
        }
        echo "<div class=\"card\"> <div class=\"card-body\"> $keys </div> </div> <br>";
        unset($_SESSION['keys_array']);
    }
    ?>

    <div class="modal fade" tabindex="-1" id="create-keys">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title">Add Licenses</h4>
                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <?php
                ($result = mysqli_query($link, "SELECT * FROM `apps` WHERE `secret` = '" . $_SESSION['app'] . "'")) or die(mysqli_error($link));
                $row = mysqli_fetch_array($result);

                $format = $row['format'];
                $amt = $row['amount'];
                $lvl = $row['lvl'];
                $note = $row['note'];
                $dur = $row['duration'];
                $unit = $row['unit'];
                ?>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Amount:</label>
                            <input type="number" min="1" class="form-control" name="amount" placeholder="Default 1"
                                value="<?php if (!is_null($amt)) {
                                                                                                                        echo $amt;
                                                                                                                    } ?>">
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Key Mask: <i
                                    class="fas fa-question-circle fa-lg text-white-50" data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Format keys are in. You can do custom by putting whatever, or do capital X or lowercase X for random character"></i></label>
                            <input type="text" class="form-control" value="<?php if (!is_null($format)) {
                                                                                echo $format;
                                                                            } else {
                                                                                echo "XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX";
                                                                            } ?>"
                                placeholder="Key Format. X is capital random char, x is lowercase" name="mask" required
                                maxlength="49">
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">License Level: <i
                                    class="fas fa-question-circle fa-lg text-white-50" data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="This needs to coordinate to the level of subscription you want to give to user when they redeem license. If it's blank, go to subscriptions tab and create subscription"></i></label>
                            <select name="level" class="form-control">
                                <?php
                                ($result = mysqli_query($link, "SELECT DISTINCT `level` FROM `subscriptions` WHERE `app` = '" . $_SESSION['app'] . "' ORDER BY `level` ASC"));
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_array($result)) {
										
										$resultSubs = mysqli_query($link, "SELECT `name` FROM `subscriptions` WHERE `level` = '" . $row["level"] . "' AND `app` = '" . $_SESSION['app'] . "'");
										
										$name = " (";
										$count = 0;
										while ($rowSubs = mysqli_fetch_array($resultSubs)) {
											$count++;
											if($count > 1) {
												$name .= ", " . $rowSubs["name"];
											}
											else {
												$name .= $rowSubs["name"];
											}
										}
										$name .= ")";
										
                                ?>
                                <option <?= $lvl == $row["level"] ? ' selected="selected"' : ''; ?>
                                    value="<?php echo $row["level"]; ?>"><?php echo $row["level"] . $name; ?></option>
                                <?php
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">License Note:</label>
                            <input type="text" class="form-control" name="note"
                                placeholder="Optional, e.g. this license was for Joe"
                                value="<?php if (!is_null($note)) {
                                                                                                                                                    echo $note;
                                                                                                                                                } ?>">
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">License Expiry Unit:</label>
                            <select name="expiry" class="form-control">
                                <option value="86400" <?= $unit == 86400 ? ' selected="selected"' : ''; ?>>Days</option>
                                <option value="60" <?= $unit == 60 ? ' selected="selected"' : ''; ?>>Minutes</option>
                                <option value="3600" <?= $unit == 3600 ? ' selected="selected"' : ''; ?>>Hours</option>
                                <option value="1" <?= $unit == 1 ? ' selected="selected"' : ''; ?>>Seconds</option>
                                <option value="604800" <?= $unit == 604800 ? ' selected="selected"' : ''; ?>>Weeks
                                </option>
                                <option value="2629743" <?= $unit == 2629743 ? ' selected="selected"' : ''; ?>>Months
                                </option>
                                <option value="31556926" <?= $unit == 31556926 ? ' selected="selected"' : ''; ?>>Years
                                </option>
                                <option value="315569260" <?= $unit == 315569260 ? ' selected="selected"' : ''; ?>>
                                    Lifetime</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">License Duration: <i
                                    class="fas fa-question-circle fa-lg text-white-50" data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="When the key is redeemed, a subscription with the duration of the key will be added to the user who redeemed the key."></i></label>
                            <input name="duration" type="number" class="form-control"
                                placeholder="Multiplied by selected Expiry unit"
                                value="<?php if (!is_null($dur)) {
                                                                                                                                                    echo $dur;
                                                                                                                                                } ?>" required>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger" name="genkeys">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" tabindex="-1" id="import-keys">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title">Import Licenses</h4>
                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Keys: <i
                                    class="fas fa-question-circle fa-lg text-white-50" data-toggle="tooltip"
                                    data-placement="top"
                                    title="Make sure you have a subscription created that matches each level of the keys you're importing."></i></label>
                            <input class="form-control" name="keys"
                                placeholder="Format: KEYHERE,LVLHERE,DAYSHERE|KEYHERE,LVLHERE,DAYSHERE">
                        </div>
						<div class="form-group">
                            <label for="recipient-name" class="control-label">Import from auth.gg:</label>
                            <input class="form-control" name="authgg"
                                placeholder="Paste in JSON from developers.auth.gg">
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger" name="importkeys">Import</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" id="comp-keys">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title">Add Time</h4>
                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Unit Of Time To Add:</label>
                            <select name="expiry" class="form-control">
                                <option value="86400">Days</option>
                                <option value="60">Minutes</option>
                                <option value="3600">Hours</option>
                                <option value="1">Seconds</option>
                                <option value="604800">Weeks</option>
                                <option value="2629743">Months</option>
                                <option value="31556926">Years</option>
                                <option value="315569260">Lifetime</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Time To Add: <i
                                    class="fas fa-question-circle fa-lg text-white-50" data-toggle="tooltip"
                                    data-placement="top"
                                    title="If the key is used, this will do nothing. Used keys are turned into users so if you want to add time to a user, go to users tab and click extend user(s)"></i></label>
                            <input class="form-control" name="time" placeholder="Multiplied by selected unit of time">
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger" name="addtime">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" tabindex="-1" id="ban-key">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title">Ban License</h4>
                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="recipient-name" class="control-label">Ban reason:</label>
                            <br>
                            <br>
                            <input type="text" class="form-control" name="reason" placeholder="Reason for ban" required>
                            <br>
                            <input class="form-check-input" style="color:white;border-color:white;" name="banUserToo"
                                type="checkbox" id="flexCheckChecked">
                            <label class="form-check-label" for="flexCheckChecked">
                                Ban User Too <i class="fas fa-question-circle fa-lg text-white-50"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Delete both the key and the user"></i>
                            </label>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger bankey" name="bankey">Ban</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" tabindex="-1" id="del-key">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title">Delete License</h4>
                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" style="color:white;border-color:white;"
                                    name="delUserToo" type="checkbox" id="flexCheckChecked">
                                <label class="form-check-label" for="flexCheckChecked">
                                    Delete User Too <i class="fas fa-question-circle fa-lg text-white-50"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Delete both the key and the user"></i>
                                </label>
                            </div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger delkey" name="deletekey">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!--begin::Modal - Delete all keys-->
    <div class="modal fade" tabindex="-1" id="delete-allkeys">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mw-900px">
            <!--begin::Modal content-->
            <div class="modal-content">
                <!--begin::Modal header-->
                <div class="modal-header">
                    <h2 class="modal-title">Delete All Keys</h2>

                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <label class="fs-5 fw-bold mb-2">
                        <p> Are you sure you want to delete all keys? </p>
                    </label>
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                        <button name="delkeys" class="btn btn-danger">Yes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal - Delete all keys-->
    <!--begin::Modal - Delete all unused keys-->
    <div class="modal fade" tabindex="-1" id="delete-allunusedkeys">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mw-900px">
            <!--begin::Modal content-->
            <div class="modal-content">
                <!--begin::Modal header-->
                <div class="modal-header">
                    <h2 class="modal-title">Delete All Unused Keys</h2>

                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <label class="fs-5 fw-bold mb-2">
                        <p> Are you sure you want to delete all unused keys? </p>
                    </label>
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                        <button name="deleteallunused" class="btn btn-danger">Yes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal - Delete all unused keys-->
    <!--begin::Modal - Delete all used keys-->
    <div class="modal fade" tabindex="-1" id="delete-allusedkeys">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mw-900px">
            <!--begin::Modal content-->
            <div class="modal-content">
                <!--begin::Modal header-->
                <div class="modal-header">
                    <h2 class="modal-title">Delete All Used Keys</h2>

                    <!--begin::Close-->
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                    transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)"
                                    fill="black" />
                            </svg>
                        </span>
                    </div>
                    <!--end::Close-->
                </div>
                <div class="modal-body">
                    <label class="fs-5 fw-bold mb-2">
                        <p> Are you sure you want to delete all used keys? </p>
                    </label>
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                        <button name="deleteallused" class="btn btn-danger">Yes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal - Delete all used keys-->
    <table id="kt_datatable_licenses" class="table table-striped table-row-bordered gy-5 gs-7 border rounded">
        <thead>
            <tr class="fw-bolder fs-6 text-gray-800 px-7">
                <th>Key</th>
                <th>Creation Date</th>
                <th>Generated By</th>
                <th>Duration</th>
                <th>Note</th>
                <th>Used On</th>
                <th>Used By</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
    </table>
    <script>
    function bankey(key) {
        var bankey = $('.bankey');
        bankey.attr('value', key);
    }

    function delkey(key) {
        var delkey = $('.delkey');
        delkey.attr('value', key);
    }
    </script>
</div>