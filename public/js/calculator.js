/**
 * Billing Calculator - Clean Implementation
 * 
 * Independent from legacy calculator-php-ui.js
 * Follows Block-Day Model with Integer Anchor Rule
 */

// ============================================
// SECTION 0: STATE MANAGEMENT
// ============================================

const CalculatorState = {
    mode: 'test',          // 'test' or 'user'
    tariffTemplate: null,  // Selected tariff template
    tariffDetails: null,   // Tariff details (tiers, rates)
    periods: [],           // Array of period objects
    readings: [],          // Array of reading objects
    startReading: 0,       // Genesis anchor (Period 1 only)
    startReadingDate: null,
    selectedUser: null,    // For user mode
    selectedAccount: null  // For user mode
};

// ============================================
// 1 - This toggles Test and User Selection
// ============================================

/**
 * Toggle between Test Mode and User Mode
 * @param {string} mode - 'test' or 'user'
 */
function toggleMode(mode) {
    console.log(`[MODE] Switching to ${mode} mode`);
    
    CalculatorState.mode = mode;
    
    // Update button states
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.mode === mode) {
            btn.classList.add('active');
        }
    });
    
    // Update panel visibility
    document.querySelectorAll('.mode-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    const targetPanel = document.getElementById(`panel-${mode}-mode`);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }
    
    // Detach event listeners for inactive mode
    if (mode === 'test') {
        // Reset user mode state
        CalculatorState.selectedUser = null;
        CalculatorState.selectedAccount = null;
    } else {
        // Reset test mode state
        CalculatorState.readings = [];
        CalculatorState.periods = [];
    }
    
    // Log state change
    if (typeof CalculatorMonitor !== 'undefined') {
        CalculatorMonitor.logState('mode_change', { mode });
    }
}

// ============================================
// 2 - This displays the start reading on the UI
// ============================================

/**
 * Display the Genesis Anchor (Start Reading) on the UI
 * Enforces Integer Anchor Rule: floor() before display
 * @param {number} reading - The start reading value
 * @param {string} date - The start reading date
 */
function displayStartReading(reading, date) {
    // INTEGER ANCHOR RULE: Always floor to integer
    const integerReading = Math.floor(Number(reading));
    
    console.log(`[GENESIS] Setting start reading: ${integerReading} L (original: ${reading})`);
    
    // Update state
    CalculatorState.startReading = integerReading;
    CalculatorState.startReadingDate = date;
    
    // Update UI
    const startReadingInput = document.getElementById('start-reading');
    const startReadingDateInput = document.getElementById('start-reading-date');
    
    if (startReadingInput) {
        startReadingInput.value = integerReading;
    }
    if (startReadingDateInput && date) {
        startReadingDateInput.value = date;
    }
    
    // Validate integer
    if (!Number.isInteger(integerReading)) {
        console.warn(`[GENESIS] WARNING: Non-integer value detected: ${reading}`);
    }
    
    // Log to monitor
    if (typeof CalculatorMonitor !== 'undefined') {
        CalculatorMonitor.logState('start_reading', { 
            original: reading, 
            floored: integerReading,
            date: date 
        });
    }
    
    return integerReading;
}

// ============================================
// 3 - This calculates the provisional
// ============================================

/**
 * Calculate provisional consumption
 * Formula: floor(Closing) - floor(Opening)
 * @param {number} closingReading - The closing reading
 * @param {number} openingReading - The opening reading
 * @returns {number} - Provisional consumption in Litres
 */
function calculateProvisional(closingReading, openingReading) {
    // INTEGER ANCHOR RULE: Floor both values
    const closing = Math.floor(Number(closingReading));
    const opening = Math.floor(Number(openingReading));
    
    // Calculate usage
    const consumption = closing - opening;
    
    // Validate: consumption must be non-negative
    if (consumption < 0) {
        console.error(`[PROVISIONAL] ERROR: Negative consumption detected! Closing (${closing}) < Opening (${opening})`);
        return null;
    }
    
    console.log(`[PROVISIONAL] ${closing} - ${opening} = ${consumption} L`);
    
    // Log to monitor
    if (typeof CalculatorMonitor !== 'undefined') {
        CalculatorMonitor.logState('provisional', {
            closing,
            opening,
            consumption
        });
    }
    
    return consumption;
}

// ============================================
// SECTION 4: DATA FETCHING
// ============================================

/**
 * Fetch tariff templates from API
 */
async function fetchTariffTemplates() {
    console.log('[API] Fetching tariff templates...');
    
    try {
        const response = await fetch('/admin/billing-calculator/tariff-templates');
        const data = await response.json();
        
        if (data.success && data.data) {
            console.log(`[API] Loaded ${data.data.length} tariff templates`);
            populateTariffDropdown(data.data);
            return data.data;
        } else {
            console.error('[API] Failed to load templates:', data.message);
            return [];
        }
    } catch (error) {
        console.error('[API] Error fetching templates:', error);
        return [];
    }
}

/**
 * Fetch tariff template details
 * @param {number} templateId - The template ID
 */
async function fetchTariffDetails(templateId) {
    console.log(`[API] Fetching details for template ${templateId}...`);
    
    try {
        const response = await fetch('/admin/billing-calculator/tariff-template-details', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ template_id: templateId })
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            console.log(`[API] Loaded tariff details:`, data.data);
            CalculatorState.tariffDetails = data.data;
            return data.data;
        } else {
            console.error('[API] Failed to load details:', data.message);
            return null;
        }
    } catch (error) {
        console.error('[API] Error fetching details:', error);
        return null;
    }
}

// ============================================
// SECTION 5: UI RENDERING
// ============================================

/**
 * Populate the tariff dropdown
 * @param {Array} templates - Array of template objects
 */
function populateTariffDropdown(templates) {
    const dropdown = document.getElementById('tariff-template');
    if (!dropdown) return;
    
    // Clear existing options (except first)
    dropdown.innerHTML = '<option value="">-- Select Tariff --</option>';
    
    templates.forEach(template => {
        const option = document.createElement('option');
        option.value = template.id;
        option.textContent = `${template.name} (${template.region_name || 'No Region'})`;
        dropdown.appendChild(option);
    });
    
    console.log(`[UI] Populated ${templates.length} tariff options`);
}

/**
 * Add a reading row to the UI
 */
function addReadingRow() {
    const container = document.getElementById('readings-container');
    if (!container) return;
    
    const readingIndex = container.children.length;
    
    const row = document.createElement('div');
    row.className = 'reading-row';
    row.innerHTML = `
        <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
            <input type="date" class="form-control reading-date" placeholder="Date" style="width: 150px;">
            <input type="number" class="form-control reading-value" placeholder="Reading (L)" min="0" style="width: 150px;">
            <button type="button" class="btn btn-danger btn-sm remove-reading-btn">X</button>
        </div>
    `;
    
    container.appendChild(row);
    
    // Add remove handler
    row.querySelector('.remove-reading-btn').addEventListener('click', () => {
        row.remove();
        console.log(`[UI] Removed reading row ${readingIndex}`);
    });
    
    console.log(`[UI] Added reading row ${readingIndex}`);
}

/**
 * Render periods table
 */
function renderPeriodsTable() {
    const tbody = document.getElementById('periods-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    CalculatorState.periods.forEach((period, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${period.startDate || '-'}</td>
            <td>${period.endDate || '-'}</td>
            <td>${Math.floor(period.opening || 0)}</td>
            <td>${Math.floor(period.closing || 0)}</td>
            <td>${period.consumption || '-'}</td>
            <td>${period.dailyRate || '-'}</td>
            <td>${period.status || 'PENDING'}</td>
        `;
        tbody.appendChild(row);
    });
    
    console.log(`[UI] Rendered ${CalculatorState.periods.length} periods`);
}

// ============================================
// SECTION 6: EVENT HANDLERS
// ============================================

function initializeEventHandlers() {
    console.log('[INIT] Setting up event handlers...');
    
    // Mode toggle buttons
    document.getElementById('btn-test-mode')?.addEventListener('click', () => toggleMode('test'));
    document.getElementById('btn-user-mode')?.addEventListener('click', () => toggleMode('user'));
    
    // Tariff selection
    document.getElementById('tariff-template')?.addEventListener('change', async (e) => {
        const templateId = e.target.value;
        if (templateId) {
            CalculatorState.tariffTemplate = templateId;
            await fetchTariffDetails(templateId);
        }
    });
    
    // Start reading change
    document.getElementById('start-reading')?.addEventListener('change', (e) => {
        displayStartReading(e.target.value, CalculatorState.startReadingDate);
    });
    
    // Add reading button
    document.getElementById('add-reading-btn')?.addEventListener('click', addReadingRow);
    
    // Calculate button
    document.getElementById('calculate-btn')?.addEventListener('click', () => {
        console.log('[ACTION] Calculate button clicked');
        // Calculation logic will be added here
    });
    
    // Add period button
    document.getElementById('add-period-btn')?.addEventListener('click', () => {
        console.log('[ACTION] Add period button clicked');
        addPeriod();
    });
    
    console.log('[INIT] Event handlers initialized');
}

/**
 * Add a new period
 */
function addPeriod() {
    const periodIndex = CalculatorState.periods.length;
    
    // Calculate dates based on billing day
    const billingDay = parseInt(document.getElementById('billing-day')?.value || 1);
    
    // Create new period object
    const newPeriod = {
        index: periodIndex,
        startDate: null,
        endDate: null,
        opening: periodIndex === 0 ? CalculatorState.startReading : null,
        closing: null,
        consumption: null,
        dailyRate: null,
        status: 'PENDING'
    };
    
    CalculatorState.periods.push(newPeriod);
    renderPeriodsTable();
    
    console.log(`[PERIOD] Added period ${periodIndex + 1}`);
}

// ============================================
// SECTION 7: INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', async () => {
    console.log('[INIT] Calculator JS loaded');
    
    // Initialize state
    CalculatorState.mode = 'test';
    CalculatorState.periods = [];
    CalculatorState.readings = [];
    
    // Set default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('period-start')?.setAttribute('value', firstDay.toISOString().split('T')[0]);
    document.getElementById('period-end')?.setAttribute('value', today.toISOString().split('T')[0]);
    document.getElementById('start-reading-date')?.setAttribute('value', firstDay.toISOString().split('T')[0]);
    
    // Initialize event handlers
    initializeEventHandlers();
    
    // Fetch tariff templates
    await fetchTariffTemplates();
    
    // Initialize monitor if available
    if (typeof CalculatorMonitor !== 'undefined') {
        CalculatorMonitor.init();
    }
    
    console.log('[INIT] Calculator ready');
});