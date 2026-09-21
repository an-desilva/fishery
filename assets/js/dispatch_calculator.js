/**
 * Catch Packing & Dispatch Calculator
 * Dynamic row addition, deletion, and real-time total calculation for Transport Logistics & Direct Buyers
 */

document.addEventListener('DOMContentLoaded', function () {
    // Transport Logistics Elements
    const catchTableBody = document.getElementById('catchTableBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const totalWeightElem = document.getElementById('totalWeightKg');
    const totalBoxesElem = document.getElementById('totalBoxCount');
    const grandTotalKgInput = document.getElementById('grandTotalKgInput');
    const grandTotalBoxesInput = document.getElementById('grandTotalBoxesInput');
    
    // Direct Buyers Elements
    const addBuyerBtn = document.getElementById('addBuyerBtn');
    const buyersContainer = document.getElementById('buyersContainer');
    const totalDirectRevenueDisplay = document.getElementById('totalDirectRevenueDisplay');
    const totalDirectWeightDisplay = document.getElementById('totalDirectWeightDisplay');
    const totalLandedCatchWeightDisplay = document.getElementById('totalLandedCatchWeightDisplay');
    const landedCatchBreakdownText = document.getElementById('landedCatchBreakdownText');
    const grandTotalDirectRevenueInput = document.getElementById('grandTotalDirectRevenueInput');
    const grandTotalDirectWeightInput = document.getElementById('grandTotalDirectWeightInput');
    const grandTotalDirectBoxesInput = document.getElementById('grandTotalDirectBoxesInput');

    // Harbour unloading inputs
    const rateTypeSelect = document.getElementById('rate_type');
    const rateAmountInput = document.getElementById('rate_amount');
    const totalLabourFeeInput = document.getElementById('total_labour_fee');

    const currencySymbol = 'Rs.';

    // Master function to calculate all totals (Lorry Transport, Direct Buyers & Landed Reconciliation)
    function calculateTotals() {
        // 1. Calculate Lorry Transport Totals
        let lorryWeight = 0;
        let lorryBoxes = 0;

        if (catchTableBody) {
            const rowWeights = catchTableBody.querySelectorAll('.row-weight');
            const rowBoxes = catchTableBody.querySelectorAll('.row-boxes');

            rowWeights.forEach(function (input) {
                lorryWeight += parseFloat(input.value) || 0;
            });

            rowBoxes.forEach(function (input) {
                lorryBoxes += parseInt(input.value) || 0;
            });
        }

        if (totalWeightElem) totalWeightElem.textContent = lorryWeight.toFixed(2) + ' Kg';
        if (totalBoxesElem) totalBoxesElem.textContent = lorryBoxes + ' Boxes';

        if (grandTotalKgInput) grandTotalKgInput.value = lorryWeight.toFixed(2);
        if (grandTotalBoxesInput) grandTotalBoxesInput.value = lorryBoxes;

        // 2. Calculate Direct Buyers Totals
        let sectionDirectWeight = 0;
        let sectionDirectBoxes = 0;
        let sectionDirectRevenue = 0;

        if (buyersContainer) {
            const buyerCards = buyersContainer.querySelectorAll('.buyer-card');

            buyerCards.forEach(function (card) {
                let cardWeight = 0;
                let cardBoxes = 0;
                let cardAmount = 0;

                const tableRows = card.querySelectorAll('.buyer-fish-table tbody tr');
                tableRows.forEach(function (row) {
                    const weight = parseFloat(row.querySelector('.buyer-item-weight')?.value) || 0;
                    const boxes = parseInt(row.querySelector('.buyer-item-boxes')?.value) || 0;
                    const rate = parseFloat(row.querySelector('.buyer-item-rate')?.value) || 0;

                    const lineTotal = weight * rate;
                    const totalInput = row.querySelector('.buyer-item-total');
                    if (totalInput) totalInput.value = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    cardWeight += weight;
                    cardBoxes += boxes;
                    cardAmount += lineTotal;
                });

                // Update Buyer Card Sub-totals
                const cardWeightElem = card.querySelector('.buyer-subtotal-weight');
                const cardBoxesElem = card.querySelector('.buyer-subtotal-boxes');
                const cardAmountElem = card.querySelector('.buyer-subtotal-amount');

                if (cardWeightElem) cardWeightElem.textContent = cardWeight.toFixed(2) + ' Kg';
                if (cardBoxesElem) cardBoxesElem.textContent = cardBoxes + ' Boxes';
                if (cardAmountElem) cardAmountElem.textContent = currencySymbol + ' ' + cardAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                sectionDirectWeight += cardWeight;
                sectionDirectBoxes += cardBoxes;
                sectionDirectRevenue += cardAmount;
            });
        }

        if (totalDirectRevenueDisplay) totalDirectRevenueDisplay.textContent = currencySymbol + ' ' + sectionDirectRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (totalDirectWeightDisplay) totalDirectWeightDisplay.textContent = sectionDirectWeight.toFixed(2) + ' Kg (' + sectionDirectBoxes + ' Boxes)';

        if (grandTotalDirectRevenueInput) grandTotalDirectRevenueInput.value = sectionDirectRevenue.toFixed(2);
        if (grandTotalDirectWeightInput) grandTotalDirectWeightInput.value = sectionDirectWeight.toFixed(2);
        if (grandTotalDirectBoxesInput) grandTotalDirectBoxesInput.value = sectionDirectBoxes;

        // 3. Reconcile Total Landed Catch Weight
        const totalLandedCatchWeight = lorryWeight + sectionDirectWeight;
        const totalLandedBoxes = lorryBoxes + sectionDirectBoxes;

        if (totalLandedCatchWeightDisplay) totalLandedCatchWeightDisplay.textContent = totalLandedCatchWeight.toFixed(2) + ' Kg (' + totalLandedBoxes + ' Boxes)';
        if (landedCatchBreakdownText) landedCatchBreakdownText.textContent = `Lorry: ${lorryWeight.toFixed(2)} Kg + Direct: ${sectionDirectWeight.toFixed(2)} Kg`;

        // 4. Recalculate harbour unloading fee based on total landed boxes
        calculateLabourFee(totalLandedBoxes);
    }

    // Calculate labour fee based on rate type
    function calculateLabourFee(totalLandedBoxes) {
        if (!rateTypeSelect || !rateAmountInput || !totalLabourFeeInput) return;

        const rateType = rateTypeSelect.value;
        const rateAmount = parseFloat(rateAmountInput.value) || 0;

        if (rateType === 'PER_BOX') {
            const boxes = totalLandedBoxes !== undefined ? totalLandedBoxes : ((parseInt(grandTotalBoxesInput ? grandTotalBoxesInput.value : 0) || 0) + (parseInt(grandTotalDirectBoxesInput ? grandTotalDirectBoxesInput.value : 0) || 0));
            totalLabourFeeInput.value = (rateAmount * boxes).toFixed(2);
        } else {
            totalLabourFeeInput.value = rateAmount.toFixed(2);
        }
    }

    if (rateTypeSelect) rateTypeSelect.addEventListener('change', calculateTotals);
    if (rateAmountInput) rateAmountInput.addEventListener('input', calculateTotals);

    // Attach listeners for transport lorry catch table rows
    function attachTransportRowListeners(row) {
        const weightInput = row.querySelector('.row-weight');
        const boxesInput = row.querySelector('.row-boxes');
        const removeBtn = row.querySelector('.remove-row-btn');

        if (weightInput) weightInput.addEventListener('input', calculateTotals);
        if (boxesInput) boxesInput.addEventListener('input', calculateTotals);

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const totalRows = catchTableBody.querySelectorAll('tr').length;
                if (totalRows > 1) {
                    row.remove();
                    reindexTransportRows();
                    calculateTotals();
                } else {
                    alert('At least one fish box row must remain.');
                }
            });
        }
    }

    function reindexTransportRows() {
        if (!catchTableBody) return;
        const rows = catchTableBody.querySelectorAll('tr');
        rows.forEach(function (row, idx) {
            row.querySelectorAll('select, input').forEach(function (input) {
                const name = input.getAttribute('name');
                if (name) {
                    const newName = name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                    input.setAttribute('name', newName);
                }
            });
        });
    }

    if (addRowBtn && catchTableBody) {
        addRowBtn.addEventListener('click', function () {
            const firstRow = catchTableBody.querySelector('tr');
            if (!firstRow) return;

            const newRow = firstRow.cloneNode(true);
            newRow.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'number') {
                    input.value = input.classList.contains('row-boxes') ? '1' : '0.00';
                } else {
                    input.value = '';
                }
            });
            newRow.querySelectorAll('select').forEach(function (select) {
                select.selectedIndex = 0;
            });

            catchTableBody.appendChild(newRow);
            attachTransportRowListeners(newRow);
            reindexTransportRows();
            calculateTotals();
        });
    }

    if (catchTableBody) {
        catchTableBody.querySelectorAll('tr').forEach(attachTransportRowListeners);
    }

    // ==========================================
    // DYNAMIC MULTI-BUYER CONTAINER LOGIC
    // ==========================================

    function createBuyerFishRowHTML(bIdx, iIdx) {
        return `
        <tr>
            <td>
                <select name="buyers[${bIdx}][items][${iIdx}][quality_grade]" class="form-select form-select-sm extra-small">
                    <option value="Grade 1 (①)">Grade 1 (① - High Export Grade)</option>
                    <option value="Grade 2 (②)">Grade 2 (② - Local Wholesale)</option>
                    <option value="Grade 3 (③)">Grade 3 (③ - Retail / Canning)</option>
                    <option value="Reject">Reject (ප්‍රතික්ෂේපිත / C-Grade)</option>
                </select>
            </td>
            <td>
                <select name="buyers[${bIdx}][items][${iIdx}][fish_species]" class="form-select form-select-sm extra-small">
                    <option value="Yellowfin Tuna (Kelawalla)">Yellowfin Tuna (Kelawalla / කෙලවල්ලා)</option>
                    <option value="Skipjack (Balaya)">Skipjack (Balaya / බලයා)</option>
                    <option value="Sailfish (Thalapatha)">Sailfish (Thalapatha / තලපතා)</option>
                    <option value="Marlin (Koppara)">Marlin (Koppara / කොප්පරා)</option>
                    <option value="Alagoduwa">Alagoduwa (අලගොඩුවා)</option>
                    <option value="Hurulla">Hurulla (හුරුල්ලා)</option>
                    <option value="Mixed / Small Fish">Mixed / Small Fish (මිශ්‍ර / කුඩා මාළු)</option>
                </select>
            </td>
            <td>
                <select name="buyers[${bIdx}][items][${iIdx}][size_category]" class="form-select form-select-sm extra-small">
                    <option value="L">L (Large / ලොකු)</option>
                    <option value="P">P (Small / පොඩි)</option>
                </select>
            </td>
            <td><input type="number" step="0.01" min="0" name="buyers[${bIdx}][items][${iIdx}][net_weight_kg]" class="form-control form-control-sm buyer-item-weight fw-bold" value="0.00"></td>
            <td><input type="number" min="1" name="buyers[${bIdx}][items][${iIdx}][box_count]" class="form-control form-control-sm buyer-item-boxes fw-bold" value="1"></td>
            <td><input type="number" step="0.01" min="0" name="buyers[${bIdx}][items][${iIdx}][rate_per_kg]" class="form-control form-control-sm buyer-item-rate fw-bold" value="0.00"></td>
            <td><input type="text" class="form-control form-control-sm buyer-item-total fw-extrabold text-success bg-light" readonly value="0.00"></td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-buyer-fish-row-btn p-1 rounded-circle"><i class="fa-solid fa-xmark"></i></button>
            </td>
        </tr>`;
    }

    function createBuyerCardHTML(bIdx) {
        return `
        <div class="card border rounded-3 buyer-card bg-light bg-opacity-50 shadow-sm">
            <div class="card-header bg-dark text-white py-2.5 d-flex align-items-center justify-content-between">
                <span class="fw-bold small"><i class="fa-solid fa-user-tag text-info me-1.5"></i> Buyer Card #<span class="buyer-number">${bIdx + 1}</span></span>
                <button type="button" class="btn btn-outline-danger btn-sm remove-buyer-btn py-0 px-2 rounded-pill extra-small text-white border-danger">
                    <i class="fa-solid fa-trash-can me-1"></i> Remove Buyer
                </button>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-secondary extra-small mb-1">Buyer Name <span class="text-danger">*</span></label>
                        <input type="text" name="buyers[${bIdx}][buyer_name]" class="form-control form-control-sm fw-bold buyer-name-input" required placeholder="e.g. Nimal Fisheries">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-secondary extra-small mb-1">Contact Phone</label>
                        <input type="text" name="buyers[${bIdx}][contact_number]" class="form-control form-control-sm font-monospace" placeholder="e.g. 0712345678">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-secondary extra-small mb-1">Vehicle / Tuk-Tuk No</label>
                        <input type="text" name="buyers[${bIdx}][vehicle_no]" class="form-control form-control-sm font-monospace" placeholder="e.g. WP ND-1234">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-secondary extra-small mb-1">Payment Method</label>
                        <select name="buyers[${bIdx}][payment_type]" class="form-select form-select-sm font-semibold">
                            <option value="CASH">Cash (තනි මුදලින්)</option>
                            <option value="BANK_TRANSFER">Bank Transfer (බැංකු හුවමාරු)</option>
                            <option value="CREDIT">Credit / Outstanding (ණය)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold extra-small text-uppercase text-secondary"><i class="fa-solid fa-fish me-1 text-primary"></i> Direct Sales Fish Rows</span>
                    <button type="button" class="btn btn-outline-success btn-sm add-buyer-fish-row-btn py-0 px-2.5 rounded-pill extra-small">
                        <i class="fa-solid fa-plus me-1"></i> Add Fish Row
                    </button>
                </div>
                <div class="table-responsive rounded-2 border bg-white mb-2">
                    <table class="table table-sm table-hover align-middle mb-0 buyer-fish-table">
                        <thead class="table-light extra-small">
                            <tr>
                                <th style="min-width: 140px;">Quality Grade</th>
                                <th style="min-width: 170px;">Fish Species</th>
                                <th style="min-width: 90px;">Size</th>
                                <th style="min-width: 110px;">Weight (Kg)</th>
                                <th style="min-width: 90px;">Boxes</th>
                                <th style="min-width: 110px;">Rate / Kg (Rs.)</th>
                                <th style="min-width: 120px;">Line Total (Rs.)</th>
                                <th style="width: 40px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${createBuyerFishRowHTML(bIdx, 0)}
                        </tbody>
                    </table>
                </div>

                <div class="p-2 rounded bg-white border d-flex flex-wrap align-items-center justify-content-between gap-2 extra-small">
                    <div>
                        <span class="text-muted me-2">Buyer Weight: <strong class="text-dark buyer-subtotal-weight">0.00 Kg</strong></span>
                        <span class="text-muted">Buyer Boxes: <strong class="text-dark buyer-subtotal-boxes">0 Boxes</strong></span>
                    </div>
                    <div>
                        <span class="fw-bold text-uppercase text-secondary me-1">Buyer Total Sales:</span>
                        <strong class="text-success fs-6 buyer-subtotal-amount">Rs. 0.00</strong>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function attachBuyerCardListeners(card) {
        const removeBuyerBtn = card.querySelector('.remove-buyer-btn');
        const addFishRowBtn = card.querySelector('.add-buyer-fish-row-btn');

        if (removeBuyerBtn) {
            removeBuyerBtn.addEventListener('click', function () {
                card.remove();
                reindexAllBuyers();
                calculateTotals();
            });
        }

        if (addFishRowBtn) {
            addFishRowBtn.addEventListener('click', function () {
                const tbody = card.querySelector('.buyer-fish-table tbody');
                if (!tbody) return;
                const bIdx = Array.from(buyersContainer.querySelectorAll('.buyer-card')).indexOf(card);
                const iIdx = tbody.querySelectorAll('tr').length;
                const tempDiv = document.createElement('tbody');
                tempDiv.innerHTML = createBuyerFishRowHTML(bIdx, iIdx);
                const newRow = tempDiv.firstElementChild;
                tbody.appendChild(newRow);
                attachBuyerRowListeners(newRow, card);
                reindexAllBuyers();
                calculateTotals();
            });
        }

        const existingFishRows = card.querySelectorAll('.buyer-fish-table tbody tr');
        existingFishRows.forEach(function (row) {
            attachBuyerRowListeners(row, card);
        });
    }

    function attachBuyerRowListeners(row, card) {
        const weightInput = row.querySelector('.buyer-item-weight');
        const boxesInput = row.querySelector('.buyer-item-boxes');
        const rateInput = row.querySelector('.buyer-item-rate');
        const removeRowBtn = row.querySelector('.remove-buyer-fish-row-btn');

        if (weightInput) weightInput.addEventListener('input', calculateTotals);
        if (boxesInput) boxesInput.addEventListener('input', calculateTotals);
        if (rateInput) rateInput.addEventListener('input', calculateTotals);

        if (removeRowBtn) {
            removeRowBtn.addEventListener('click', function () {
                const tbody = row.closest('tbody');
                const totalRows = tbody ? tbody.querySelectorAll('tr').length : 0;
                if (totalRows > 1) {
                    row.remove();
                    reindexAllBuyers();
                    calculateTotals();
                } else {
                    alert('Each buyer must have at least one fish sales row.');
                }
            });
        }
    }

    function reindexAllBuyers() {
        if (!buyersContainer) return;
        const buyerCards = buyersContainer.querySelectorAll('.buyer-card');
        buyerCards.forEach(function (card, bIdx) {
            const buyerNumberElem = card.querySelector('.buyer-number');
            if (buyerNumberElem) buyerNumberElem.textContent = bIdx + 1;

            // Update top-level buyer fields
            card.querySelectorAll('input, select').forEach(function (input) {
                const name = input.getAttribute('name');
                if (name && name.startsWith('buyers[')) {
                    let updatedName = name.replace(/^buyers\[\d+\]/, `buyers[${bIdx}]`);
                    input.setAttribute('name', updatedName);
                }
            });

            // Update buyer fish table rows
            const fishRows = card.querySelectorAll('.buyer-fish-table tbody tr');
            fishRows.forEach(function (row, iIdx) {
                row.querySelectorAll('input, select').forEach(function (input) {
                    const name = input.getAttribute('name');
                    if (name && name.includes('[items][')) {
                        let updatedName = name.replace(/buyers\[\d+\]\[items\]\[\d+\]/, `buyers[${bIdx}][items][${iIdx}]`);
                        input.setAttribute('name', updatedName);
                    }
                });
            });
        });
    }

    if (addBuyerBtn && buyersContainer) {
        addBuyerBtn.addEventListener('click', function () {
            const bIdx = buyersContainer.querySelectorAll('.buyer-card').length;
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = createBuyerCardHTML(bIdx);
            const newCard = tempDiv.firstElementChild;
            buyersContainer.appendChild(newCard);
            attachBuyerCardListeners(newCard);
            reindexAllBuyers();
            calculateTotals();
        });
    }

    // Initialize existing buyer cards rendered by PHP
    if (buyersContainer) {
        const existingBuyerCards = buyersContainer.querySelectorAll('.buyer-card');
        existingBuyerCards.forEach(attachBuyerCardListeners);
    }

    // Initial Master Calculation
    calculateTotals();
});
