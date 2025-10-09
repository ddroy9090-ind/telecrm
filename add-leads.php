<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
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
                <form action="#" method="post" class="row g-3" novalidate>
                    <div class="col-md-3">
                        <label for="stage" class="form-label">Stage</label>
                        <select id="stage" name="stage" class="form-select" data-choices>
                            <option value="" selected disabled>Select stage</option>
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Negotiation">Negotiation</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="rating" class="form-label">Rating</label>
                        <select id="rating" name="rating" class="form-select" data-choices>
                            <option value="" selected disabled>Select rating</option>
                            <option value="Hot">Hot</option>
                            <option value="Warm">Warm</option>
                            <option value="Cold">Cold</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="assignedTo" class="form-label">Assigned To</label>
                        <select id="assignedTo" name="assigned_to" class="form-select" data-choices>
                            <option value="" selected disabled>Select assignee</option>
                            <option value="Unassigned">Unassigned</option>
                            <option value="Agent 1">Agent 1</option>
                            <option value="Agent 2">Agent 2</option>
                            <option value="Agent 3">Agent 3</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="source" class="form-label">Source</label>
                        <select id="source" name="source" class="form-select" data-choices>
                            <option value="" selected disabled>Select source</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Advertisement">Advertisement</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="leadName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="leadName" name="name">
                    </div>

                    <div class="col-md-6">
                        <label for="leadPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="leadPhone" name="phone">
                    </div>

                    <div class="col-md-6">
                        <label for="leadEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="leadEmail" name="email">
                    </div>

                    <div class="col-md-6">
                        <label for="alternatePhone" class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" id="alternatePhone" name="alternate_phone">
                    </div>

                    <div class="col-md-6">
                        <label for="nationality" class="form-label">Nationality</label>
                        <select id="nationality" name="nationality" class="form-select" data-choices>
                            <option value="" selected disabled>Select nationality</option>
                            <option value="India">India</option>
                            <option value="UAE">UAE</option>
                            <option value="UK">UK</option>
                            <option value="Australia">Australia</option>
                            <option value="Canada">Canada</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="interestedIn" class="form-label">Interested In</label>
                        <select id="interestedIn" name="interested_in" class="form-select" data-choices>
                            <option value="" selected disabled>Select interest</option>
                            <option value="Buy">Buy</option>
                            <option value="Sell">Sell</option>
                            <option value="Rent">Rent</option>
                            <option value="Invest">Invest</option>
                            <option value="Lease">Lease</option>
                            <option value="India">India</option>
                            <option value="UK">UK</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="propertyType" class="form-label">Property Type</label>
                        <select id="propertyType" name="property_type" class="form-select" data-choices>
                            <option value="" selected disabled>Select property type</option>
                            <option value="Apartment">Apartment</option>
                            <option value="Villa">Villa</option>
                            <option value="Plot">Plot</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Land">Land</option>
                            <option value="Off Plan">Off Plan</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="locationPreferences" class="form-label">Location Preferences</label>
                        <input type="text" class="form-control" id="locationPreferences" name="location_preferences">
                    </div>

                    <div class="col-md-6">
                        <label for="budgetRange" class="form-label">Budget Range</label>
                        <input type="text" class="form-control" id="budgetRange" name="budget_range">
                    </div>

                    <div class="col-md-6">
                        <label for="sizeRequired" class="form-label">Size Required</label>
                        <input type="text" class="form-control" id="sizeRequired" name="size_required">
                    </div>

                    <div class="col-md-6">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose">
                    </div>

                    <div class="col-md-6">
                        <label for="urgency" class="form-label">Urgency of Purchase</label>
                        <select id="urgency" name="urgency" class="form-select" data-choices>
                            <option value="" selected disabled>Select urgency level</option>
                            <option value="Immediate">Immediate</option>
                            <option value="1-3 Months">1-3 Months</option>
                            <option value="3-6 Months">3-6 Months</option>
                            <option value="Flexible">Flexible</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="alternateEmail" class="form-label">Alternate Email</label>
                        <input type="email" class="form-control" id="alternateEmail" name="alternate_email">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="payoutReceived" name="payout_received">
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
