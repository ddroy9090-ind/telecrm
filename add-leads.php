<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$successMessage = '';
$errorMessage = '';
$formData = [
    'stage' => '',
    'rating' => '',
    'assigned_to' => '',
    'source' => '',
    'name' => '',
    'phone' => '',
    'email' => '',
    'alternate_phone' => '',
    'nationality' => '',
    'interested_in' => '',
    'property_type' => '',
    'location_preferences' => '',
    'budget_range' => '',
    'size_required' => '',
    'purpose' => '',
    'urgency' => '',
    'alternate_email' => '',
];
$payoutReceivedInput = '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $default) {
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    $payoutReceived = isset($_POST['payout_received']) ? 1 : 0;
    $payoutReceivedInput = $payoutReceived ? '1' : '0';

    $stmt = $mysqli->prepare(
        "INSERT INTO `All leads` (stage, rating, assigned_to, source, name, phone, email, alternate_phone, nationality, interested_in, property_type, location_preferences, budget_range, size_required, purpose, urgency, alternate_email, payout_received) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if ($stmt) {
        $stmt->bind_param(
            'sssssssssssssssssi',
            $formData['stage'],
            $formData['rating'],
            $formData['assigned_to'],
            $formData['source'],
            $formData['name'],
            $formData['phone'],
            $formData['email'],
            $formData['alternate_phone'],
            $formData['nationality'],
            $formData['interested_in'],
            $formData['property_type'],
            $formData['location_preferences'],
            $formData['budget_range'],
            $formData['size_required'],
            $formData['purpose'],
            $formData['urgency'],
            $formData['alternate_email'],
            $payoutReceived
        );

        if ($stmt->execute()) {
            $successMessage = 'Lead has been added successfully.';
            foreach ($formData as $key => $_) {
                $formData[$key] = '';
            }
            $payoutReceivedInput = '0';
        } else {
            $errorMessage = 'Failed to add lead: ' . $stmt->error;
        }

        $stmt->close();
    } else {
        $errorMessage = 'Failed to prepare statement: ' . $mysqli->error;
    }
}

?>
<?php include 'includes/common-header.php'; ?>

<div id="adminPanel">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Add Lead</h1>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>
                <form action="" method="post" class="row g-3" novalidate>
                    <div class="col-md-3">
                        <label for="stage" class="form-label">Stage</label>
                        <select id="stage" name="stage" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['stage'] === '' ? 'selected' : ''; ?>>Select stage</option>
                            <option value="New" <?php echo $formData['stage'] === 'New' ? 'selected' : ''; ?>>New</option>
                            <option value="Contacted" <?php echo $formData['stage'] === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="Qualified" <?php echo $formData['stage'] === 'Qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="Proposal" <?php echo $formData['stage'] === 'Proposal' ? 'selected' : ''; ?>>Proposal</option>
                            <option value="Negotiation" <?php echo $formData['stage'] === 'Negotiation' ? 'selected' : ''; ?>>Negotiation</option>
                            <option value="Closed" <?php echo $formData['stage'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="rating" class="form-label">Rating</label>
                        <select id="rating" name="rating" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['rating'] === '' ? 'selected' : ''; ?>>Select rating</option>
                            <option value="Hot" <?php echo $formData['rating'] === 'Hot' ? 'selected' : ''; ?>>Hot</option>
                            <option value="Warm" <?php echo $formData['rating'] === 'Warm' ? 'selected' : ''; ?>>Warm</option>
                            <option value="Cold" <?php echo $formData['rating'] === 'Cold' ? 'selected' : ''; ?>>Cold</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="assignedTo" class="form-label">Assigned To</label>
                        <select id="assignedTo" name="assigned_to" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['assigned_to'] === '' ? 'selected' : ''; ?>>Select assignee</option>
                            <option value="Unassigned" <?php echo $formData['assigned_to'] === 'Unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                            <option value="Agent 1" <?php echo $formData['assigned_to'] === 'Agent 1' ? 'selected' : ''; ?>>Agent 1</option>
                            <option value="Agent 2" <?php echo $formData['assigned_to'] === 'Agent 2' ? 'selected' : ''; ?>>Agent 2</option>
                            <option value="Agent 3" <?php echo $formData['assigned_to'] === 'Agent 3' ? 'selected' : ''; ?>>Agent 3</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="source" class="form-label">Source</label>
                        <select id="source" name="source" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['source'] === '' ? 'selected' : ''; ?>>Select source</option>
                            <option value="Website" <?php echo $formData['source'] === 'Website' ? 'selected' : ''; ?>>Website</option>
                            <option value="Referral" <?php echo $formData['source'] === 'Referral' ? 'selected' : ''; ?>>Referral</option>
                            <option value="Walk-in" <?php echo $formData['source'] === 'Walk-in' ? 'selected' : ''; ?>>Walk-in</option>
                            <option value="Social Media" <?php echo $formData['source'] === 'Social Media' ? 'selected' : ''; ?>>Social Media</option>
                            <option value="Advertisement" <?php echo $formData['source'] === 'Advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="leadName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="leadName" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="leadPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="leadPhone" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="leadEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="leadEmail" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="alternatePhone" class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" id="alternatePhone" name="alternate_phone" value="<?php echo htmlspecialchars($formData['alternate_phone']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="nationality" class="form-label">Nationality</label>
                        <select id="nationality" name="nationality" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['nationality'] === '' ? 'selected' : ''; ?>>Select nationality</option>
                            <option value="India" <?php echo $formData['nationality'] === 'India' ? 'selected' : ''; ?>>India</option>
                            <option value="UAE" <?php echo $formData['nationality'] === 'UAE' ? 'selected' : ''; ?>>UAE</option>
                            <option value="UK" <?php echo $formData['nationality'] === 'UK' ? 'selected' : ''; ?>>UK</option>
                            <option value="Australia" <?php echo $formData['nationality'] === 'Australia' ? 'selected' : ''; ?>>Australia</option>
                            <option value="Canada" <?php echo $formData['nationality'] === 'Canada' ? 'selected' : ''; ?>>Canada</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="interestedIn" class="form-label">Interested In</label>
                        <select id="interestedIn" name="interested_in" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['interested_in'] === '' ? 'selected' : ''; ?>>Select interest</option>
                            <option value="Buy" <?php echo $formData['interested_in'] === 'Buy' ? 'selected' : ''; ?>>Buy</option>
                            <option value="Sell" <?php echo $formData['interested_in'] === 'Sell' ? 'selected' : ''; ?>>Sell</option>
                            <option value="Rent" <?php echo $formData['interested_in'] === 'Rent' ? 'selected' : ''; ?>>Rent</option>
                            <option value="Invest" <?php echo $formData['interested_in'] === 'Invest' ? 'selected' : ''; ?>>Invest</option>
                            <option value="Lease" <?php echo $formData['interested_in'] === 'Lease' ? 'selected' : ''; ?>>Lease</option>
                            <option value="India" <?php echo $formData['interested_in'] === 'India' ? 'selected' : ''; ?>>India</option>
                            <option value="UK" <?php echo $formData['interested_in'] === 'UK' ? 'selected' : ''; ?>>UK</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="propertyType" class="form-label">Property Type</label>
                        <select id="propertyType" name="property_type" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['property_type'] === '' ? 'selected' : ''; ?>>Select property type</option>
                            <option value="Apartment" <?php echo $formData['property_type'] === 'Apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="Villa" <?php echo $formData['property_type'] === 'Villa' ? 'selected' : ''; ?>>Villa</option>
                            <option value="Plot" <?php echo $formData['property_type'] === 'Plot' ? 'selected' : ''; ?>>Plot</option>
                            <option value="Commercial" <?php echo $formData['property_type'] === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                            <option value="Land" <?php echo $formData['property_type'] === 'Land' ? 'selected' : ''; ?>>Land</option>
                            <option value="Off Plan" <?php echo $formData['property_type'] === 'Off Plan' ? 'selected' : ''; ?>>Off Plan</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="locationPreferences" class="form-label">Location Preferences</label>
                        <input type="text" class="form-control" id="locationPreferences" name="location_preferences" value="<?php echo htmlspecialchars($formData['location_preferences']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="budgetRange" class="form-label">Budget Range</label>
                        <input type="text" class="form-control" id="budgetRange" name="budget_range" value="<?php echo htmlspecialchars($formData['budget_range']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="sizeRequired" class="form-label">Size Required</label>
                        <input type="text" class="form-control" id="sizeRequired" name="size_required" value="<?php echo htmlspecialchars($formData['size_required']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" value="<?php echo htmlspecialchars($formData['purpose']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="urgency" class="form-label">Urgency of Purchase</label>
                        <select id="urgency" name="urgency" class="form-select" data-choices>
                            <option value="" disabled <?php echo $formData['urgency'] === '' ? 'selected' : ''; ?>>Select urgency level</option>
                            <option value="Immediate" <?php echo $formData['urgency'] === 'Immediate' ? 'selected' : ''; ?>>Immediate</option>
                            <option value="1-3 Months" <?php echo $formData['urgency'] === '1-3 Months' ? 'selected' : ''; ?>>1-3 Months</option>
                            <option value="3-6 Months" <?php echo $formData['urgency'] === '3-6 Months' ? 'selected' : ''; ?>>3-6 Months</option>
                            <option value="Flexible" <?php echo $formData['urgency'] === 'Flexible' ? 'selected' : ''; ?>>Flexible</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="alternateEmail" class="form-label">Alternate Email</label>
                        <input type="email" class="form-control" id="alternateEmail" name="alternate_email" value="<?php echo htmlspecialchars($formData['alternate_email']); ?>">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="payoutReceived" name="payout_received" <?php echo $payoutReceivedInput === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payoutReceived">
                                Payout Received from Builder
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Submit Lead</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>
