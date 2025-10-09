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
                    <div class="col-md-6">
                        <label for="leadName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="leadName" name="name" placeholder="Enter full name">
                    </div>

                    <div class="col-md-6">
                        <label for="leadPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="leadPhone" name="phone" placeholder="e.g. +971 50 123 4567">
                    </div>

                    <div class="col-md-6">
                        <label for="leadEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="leadEmail" name="email" placeholder="name@example.com">
                    </div>

                    <div class="col-md-6">
                        <label for="alternatePhone" class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" id="alternatePhone" name="alternate_phone" placeholder="Alternate contact number">
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
                        <input type="text" class="form-control" id="locationPreferences" name="location_preferences" placeholder="Preferred locations">
                    </div>

                    <div class="col-md-6">
                        <label for="budgetRange" class="form-label">Budget Range</label>
                        <input type="text" class="form-control" id="budgetRange" name="budget_range" placeholder="e.g. AED 1M - AED 1.5M">
                    </div>

                    <div class="col-md-6">
                        <label for="sizeRequired" class="form-label">Size Required</label>
                        <input type="text" class="form-control" id="sizeRequired" name="size_required" placeholder="Desired size">
                    </div>

                    <div class="col-md-6">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" placeholder="Purpose of purchase">
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
                        <input type="email" class="form-control" id="alternateEmail" name="alternate_email" placeholder="Alternate email address">
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
