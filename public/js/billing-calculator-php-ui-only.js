/**
 * Billing Calculator - PHP Only (UI Management)
 * 
 * This file contains ONLY UI management functions.
 * ALL calculations are performed by PHP server-side.
 * 
 * NO calculation logic exists in this file.
 */

// ==================== GLOBAL STATE (UI Only - No Calculations) ====================
let periods = []; // Array of period objects: {start, end, status, readings: [{date, value}], ...}
let active = null; // Index of currently active period
let currentTariffTemplate = null;
let currentTemplateTiers = [];
let allTariffTemplates = []; // Store all templates for filtering (shared with blade file)

// ==================== UTILITY FUNCTIONS ====================

function formatDate(date) {
    if (!date) return '—';
    
    // Handle datetime strings (e.g., "2026-01-01 12:00:00") by extracting just the date part
    let dateStr = date;
    if (typeof date === 'string') {
        // If it's a datetime string, extract just the date part (YYYY-MM-DD)
        if (date.includes(' ')) {
            dateStr = date.split(' ')[0];
        }
        // Parse the date string to ensure it's valid
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) {
            return date; // Return original if invalid
        }
        const day = d.getDate();
        const month = d.toLocaleString('en-GB', { month: 'long' });
        const year = d.getFullYear();
        const daySuffix = day === 1 ? 'st' : day === 2 ? 'nd' : day === 3 ? 'rd' : 'th';
        return `${day}${daySuffix} ${month} ${year}`;
    }
    
    // Handle Date objects
    const d = new Date(date);
    if (isNaN(d.getTime())) return '—';
    const day = d.getDate();
    const month = d.toLocaleString('en-GB', { month: 'long' });
    const year = d.getFullYear();
    const daySuffix = day === 1 ? 'st' : day === 2 ? 'nd' : day === 3 ? 'rd' : 'th';
    return `${day}${daySuffix} ${month} ${year}`;
}

function formatDateRange(start, end) {
    if (!start || !end) return '—';
    
    // Format start date: "1st Feb 2026"
    const startDate = new Date(start);
    const startDay = startDate.getDate();
    const startMonth = startDate.toLocaleString('en-GB', { month: 'short' });
    const startYear = startDate.getFullYear();
    const startDaySuffix = startDay === 1 ? 'st' : startDay === 2 ? 'nd' : startDay === 3 ? 'rd' : 'th';
    const startFormatted = `${startDay}${startDaySuffix} ${startMonth} ${startYear}`;
    
    // Format end date: "28th Feb" (no year)
    const endDate = new Date(end);
    endDate.setDate(endDate.getDate() - 1); // End is exclusive
    const endDay = endDate.getDate();
    const endMonth = endDate.toLocaleString('en-GB', { month: 'short' });
    const endDaySuffix = endDay === 1 ? 'st' : endDay === 2 ? 'nd' : endDay === 3 ? 'rd' : 'th';
    const endFormatted = `${endDay}${endDaySuffix} ${endMonth}`;
    
    return `${startFormatted} to ${endFormatted}`;
}

/**
 * Format currency amount for display
 * @param {number|null|undefined} amount
 * @returns {string}
 */
function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) return 'R 0.00';
    return 'R ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

/**
 * Format tier max value (handles Infinity and null for unlimited tiers)
 * @param {number|null|Infinity} max
 * @returns {string|number}
 */
function formatTierMax(max) {
    return (max === Infinity || max === null) ? '∞' : max;
}

/**
 * Format number for display (litres)
 * @param {number|null|undefined} num
 * @returns {string}
 */
function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    // Round to nearest integer for display (litres)
    const rounded = Math.round(num);
    return new Intl.NumberFormat('en-ZA').format(rounded);
}

/**
 * NOTE: All calculations are now performed server-side by PHP.
 * This file only handles UI rendering of PHP-provided values.
 */

// ==================== PERIOD MANAGEMENT (UI Only) ====================

/**
 * Add a new period (UI only - no calculations)
 */
function add_period() {
    try {
        const billDayEl = document.getElementById('bill_day');
        const startMonthEl = document.getElementById('start_month');
        
        if (!billDayEl) throw new Error('bill_day element not found');
        if (!startMonthEl) throw new Error('start_month element not found');
        
        const billDay = Number(billDayEl.value);
        const startMonthValue = startMonthEl.value;
        
        if (!startMonthValue) throw new Error('start_month value is empty');
        
        const [yearStr, monthStr] = startMonthValue.split('-');
        const startYear = Number(yearStr);
        const startMonth = Number(monthStr);
        
        let start, end;
        
        if (periods.length === 0) {
            // First period: start on current month's bill day, end on next month's bill day (exclusive)
            start = new Date(startYear, startMonth - 1, billDay, 12, 0, 0);
            end = new Date(startYear, startMonth, billDay, 12, 0, 0);
        } else {
            // Subsequent periods: start where previous ended, end one month later
            const prevPeriod = periods[periods.length - 1];
            if (!prevPeriod || !prevPeriod.end) {
                throw new Error('Previous period missing end date');
            }
            
            // VALIDATION RULE: If we have 2+ readings, opening reading is REQUIRED
            // Check if previous period has 2+ readings with both date and value
            const prevReadings = (prevPeriod.readings || []).filter(r => 
                r.date !== null && r.date !== undefined && 
                r.value !== null && r.value !== undefined
            );
            const hasTwoOrMoreReadings = prevReadings.length >= 2;
            
            if (hasTwoOrMoreReadings) {
                // Opening reading is REQUIRED - must come from previous period's effective closing
                // DIRECTIVE COMPLIANCE: Use calculated_closing if reconciled, otherwise provisional_closing
                // If Period 1 hasn't been calculated yet, we still need to prevent Period 2 creation
                // because once we have 2+ readings, we can calculate dailyUsage, and opening reading becomes mandatory
                const isReconciled = prevPeriod.is_reconciled === true;
                const openingReading = (isReconciled && prevPeriod.calculated_closing !== null && prevPeriod.calculated_closing !== undefined)
                    ? prevPeriod.calculated_closing
                    : (prevPeriod.provisional_closing ?? null);
                
                if (openingReading === null || openingReading === 0) {
                    alert('Cannot create new period: Previous period has 2 or more readings but no effective closing reading. ' +
                          'Please calculate the previous period first to establish the opening reading for this period.');
                    return;
                }
            }
            
            start = new Date(prevPeriod.end);
            end = new Date(start);
            end.setMonth(end.getMonth() + 1);
            end.setDate(billDay);
            end.setHours(12, 0, 0);
        }
        
        // Compute display boundaries (matches PeriodBoundaryContract logic)
        const displayStart = new Date(start);
        displayStart.setHours(12, 0, 0, 0); // Normalize to 12:00:00
        const displayEnd = new Date(end);
        displayEnd.setDate(displayEnd.getDate() - 1); // display_end = period.end - 1 day
        displayEnd.setHours(12, 0, 0, 0); // Normalize to 12:00:00
        
        // Add period (NO calculations - just UI state)
        periods.push({
            start: start,
            end: end,
            display_start: displayStart.toISOString(), // Pipeline-defined boundary (inclusive)
            display_end: displayEnd.toISOString(),     // Pipeline-defined boundary (inclusive)
            status: 'PROVISIONAL',
            readings: [],
            opening: null,
            provisional_closing: null,
            calculated_closing: null,
            usage: null,
            dailyUsage: null,
            original_provisional_usage: null,
            sectors: []
        });
        
        // Set as active
        active = periods.length - 1;
        
        // Update UI
        renderPeriodsTable();
        renderReadingsTable(); // This also calls updateAddReadingButtonState
        updateDashboard({ periods: periods });
        renderCalculationOutput({ periods: periods });
        updateCalculateButtonState();
        updateAddReadingButtonState(); // Ensure button state is updated
        
    } catch (error) {
        alert('Error adding period: ' + error.message);
        console.error('add_period error:', error);
    }
}

/**
 * Add a reading to the active period (UI only - no calculations)
 */
function add_reading() {
    try {
        if (active === null || active < 0 || active >= periods.length) {
            throw new Error('No active period. Please add a period first.');
        }
        
        const period = periods[active];
        
        // DIRECTIVE COMPLIANCE: Mutability is derived from billing_state only
        // is_mutable = (billing_state === PROVISIONAL)
        // CALCULATED periods are immutable and cannot accept new readings
        if (period.status === 'CALCULATED') {
            alert('Cannot add readings to a CALCULATED period. CALCULATED periods are immutable and cannot be modified.');
            return;
        }
        
        // Add empty reading (user will fill in date and value)
        period.readings.push({
            date: null,
            value: null
        });
        
        // Update UI
        renderReadingsTable();
        updateCalculateButtonState();
        updateAddReadingButtonState();
        
    } catch (error) {
        alert('Error adding reading: ' + error.message);
        console.error('add_reading error:', error);
    }
}

/**
 * Delete a reading (UI only)
 */
function delete_reading(periodIndex, readingIndex) {
    try {
        if (periods[periodIndex] && periods[periodIndex].readings) {
            periods[periodIndex].readings.splice(readingIndex, 1);
            renderReadingsTable();
            updateCalculateButtonState();
        }
    } catch (error) {
        console.error('delete_reading error:', error);
    }
}

/**
 * Delete a period (UI only)
 */
function delete_period(periodIndex) {
    try {
        if (confirm('Are you sure you want to delete this period?')) {
            periods.splice(periodIndex, 1);
            if (active >= periods.length) {
                active = periods.length > 0 ? periods.length - 1 : null;
            }
            renderPeriodsTable();
            renderReadingsTable(); // This also calls updateAddReadingButtonState
            updateDashboard({ periods: periods });
            renderCalculationOutput({ periods: periods });
            updateCalculateButtonState();
            updateAddReadingButtonState(); // Ensure button state is updated
        }
    } catch (error) {
        console.error('delete_period error:', error);
    }
}

// ==================== UI RENDERING ====================

/**
 * Render periods table
 */
function renderPeriodsTable() {
    const tbody = document.querySelector('#period_table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    periods.forEach((period, idx) => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        
        // Check if this is the active period
        const isActive = active === idx;
        
        // Only active period is light blue, all others are grey
        const backgroundColor = isActive ? '#acd6e4' : '#D9D9D9';
        const textColor = '#000000'; // Black text for both
        
        row.style.backgroundColor = backgroundColor;
        row.style.color = textColor;
        row.style.border = 'none'; // No cell borders
        row.style.borderRadius = '8px';
        row.style.height = 'auto';
        row.style.margin = '12px 0';
        
        row.onclick = () => {
            active = idx;
            // Re-render all UI components to reflect the active period
            renderPeriodsTable();
            renderReadingsTable();
            updateDashboard({ periods: periods });
            renderCalculationOutput({ periods: periods });
            updateAddReadingButtonState();
        };
        
        const periodRange = formatDateRange(period.start, period.end);
        
        // Format opening reading (handle Period 1 which uses start_reading)
        const openingValue = idx === 0 && period.start_reading !== null && period.start_reading !== undefined
            ? formatNumber(period.start_reading)
            : (period.opening !== null && period.opening !== undefined 
                ? formatNumber(period.opening) 
                : '—');
        
        // DIRECTIVE COMPLIANCE: Use provisional_closing for display (calculated_closing only if reconciled)
        // Format provisional closing reading
        let closingValue = '—';
        if (period.provisional_closing !== null && period.provisional_closing !== undefined) {
            closingValue = formatNumber(period.provisional_closing);
        }
        
        // DIRECTIVE COMPLIANCE: calculated_closing must ONLY be displayed if period is reconciled
        // For unreconciled periods, calculated_closing must be null and not displayed
        let calculatedValue = '—';
        const isReconciled = period.is_reconciled === true;
        if (isReconciled && period.calculated_closing !== null && period.calculated_closing !== undefined) {
            calculatedValue = formatNumber(period.calculated_closing);
        }
        
        // Calculate usage value
        const usageValue = (period.usage !== null && period.usage !== undefined) 
            ? formatNumber(period.usage) 
            : '—';
        
        // Status badge styling - Use display_status for UI (READING REQUIRED / PROVISIONAL / CALCULATED)
        // display_status is computed by backend based on: provisional_closing/usage/dailyUsage null → READING REQUIRED
        // DIRECTIVE COMPLIANCE: Only PROVISIONAL and CALCULATED are valid billing states
        // UNRESOLVED, ACTUAL, OPEN, CLOSED are forbidden states and must not be displayed
        const displayStatus = period.display_status || period.status || 'PROVISIONAL';
        // Normalize display status: map any forbidden states to PROVISIONAL
        const normalizedStatus = (displayStatus === 'PROVISIONAL' || displayStatus === 'CALCULATED') 
            ? displayStatus 
            : (displayStatus === 'READING REQUIRED' ? 'READING REQUIRED' : 'PROVISIONAL');
        let statusBadge = '';
        if (normalizedStatus === 'READING REQUIRED') {
            statusBadge = `<span style="display:inline-block; padding:4px 10px; background:#EF4444; color:#FFFFFF; border-radius:6px; font-size:14px; font-weight:700;">READING REQUIRED</span>`;
        } else if (normalizedStatus === 'CALCULATED') {
            statusBadge = `<span style="display:inline-block; padding:4px 10px; background:#0D8E32; color:#FFFFFF; border-radius:6px; font-size:14px; font-weight:700;">CALCULATED</span>`;
        } else if (normalizedStatus === 'PROVISIONAL') {
            statusBadge = `<span style="display:inline-block; padding:4px 10px; background:#F4B62F; color:#000000; border-radius:6px; font-size:14px; font-weight:700;">PROVISIONAL</span>`;
        } else {
            // Fallback: treat any unknown state as PROVISIONAL
            statusBadge = `<span style="display:inline-block; padding:4px 10px; background:#F4B62F; color:#000000; border-radius:6px; font-size:14px; font-weight:700;">PROVISIONAL</span>`;
        }
        
        // Layout matching image: Left column (Period, Date, Usage) | Right column (Opening, Closing, Calculated) | Status badge
        row.innerHTML = `
            <td style="padding:12px 20px; border:none; vertical-align:middle; text-align:left;">
                <div style="font-size:16px; font-weight:700; margin-bottom:4px;">Period ${idx + 1} -</div>
                <div style="font-size:14px; color:${textColor}; margin-bottom:4px;">${periodRange}</div>
                <div style="font-size:14px; color:${textColor};">
                    Usage: <strong>${usageValue} L</strong>
                </div>
            </td>
            <td style="padding:12px 20px; border:none; vertical-align:middle; text-align:left;">
                <div style="font-size:14px; margin-bottom:4px;">
                    Opening: <strong>${openingValue}</strong>
                </div>
                <div style="font-size:14px; margin-bottom:4px;">
                    Provisional Closing: <strong>${closingValue}</strong>
                </div>
                <div style="font-size:14px;">
                    Calculated Closing: <strong>${calculatedValue}</strong>
                </div>
            </td>
            <td style="padding:12px 20px; border:none; vertical-align:middle; text-align:right;">
                ${statusBadge}
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Add "Add Period" button after the last period (matching image)
    // Always show the button, even if no periods exist
    const buttonRow = document.createElement('tr');
    buttonRow.innerHTML = `
        <td colspan="3" style="padding:12px 0; text-align:center; border:none;">
            <button onclick="window.add_period()" style="background:#2f91b9; color:white; border:none; padding:10px 20px; border-radius:6px; font-size:16px; cursor:pointer;">Add Period</button>
        </td>
    `;
    buttonRow.style.border = 'none';
    tbody.appendChild(buttonRow);
}

/**
 * Render period calendar view - REMOVED (replaced with dropdown datepicker)
 * This function is kept empty to prevent errors from existing calls
 */
function renderPeriodCalendar() {
    // Calendar display removed - using dropdown datepicker instead
    const calendarContainer = document.getElementById('period_calendar_view');
    if (calendarContainer) {
        calendarContainer.innerHTML = '';
    }
}

/**
 * Render readings table for active period
 * 
 * NOTE: This function is ONLY for PERIOD-TO-PERIOD mode.
 * Date-to-date mode uses a separate table (#sector_reading_table) and date picker (#sector_date_picker).
 * The dropdown datepicker in this function will NOT be used for date-to-date periods.
 */
function renderReadingsTable() {
    const tbody = document.querySelector('#period_reading_table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (active === null || active < 0 || active >= periods.length) {
        tbody.innerHTML = '';
        renderPeriodCalendar(); // Clear calendar container
        return;
    }
    
    renderPeriodCalendar(); // Clear calendar container
    
    const period = periods[active];
    const readings = period.readings || [];
    // DIRECTIVE COMPLIANCE: Mutability is derived from billing_state only
    // is_mutable = (billing_state === PROVISIONAL)
    // CALCULATED periods are immutable
    const isImmutable = period.status === 'CALCULATED';
    
    readings.forEach((reading, idx) => {
        const row = document.createElement('tr');
        
        // Date dropdown selector (replaces native date input)
        // IMPORTANT: This dropdown is ONLY for PERIOD-TO-PERIOD mode.
        // Date-to-date mode uses #sector_date_picker (native date input) which can handle long date ranges.
        const dateSelect = document.createElement('select');
        dateSelect.className = 'input-date';
        dateSelect.style.width = '100%';
        dateSelect.style.padding = '8px';
        dateSelect.style.border = '1px solid var(--border)';
        dateSelect.style.borderRadius = '4px';
        dateSelect.style.fontSize = '14px';
        dateSelect.style.backgroundColor = '#fff';
        
        // Add empty option
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '-- Select Date --';
        dateSelect.appendChild(emptyOption);
        
        // PERIOD TO PERIOD ONLY: Populate dropdown with all available period dates
        // This dropdown is designed for periods that typically span 1-2 months (e.g., 20th Jan to 19th Feb)
        // Date-to-date periods can span many months and use a different date picker (#sector_date_picker)
        // Use pipeline-defined display boundaries (preferred) or compute from period.start/end (fallback)
        // All dates in period are selectable, including period start date (even if in past)
        let periodStart, periodEnd;
        
        if (period.display_start && period.display_end) {
            // Use pipeline-defined boundaries (preferred)
            periodStart = new Date(period.display_start);
            periodEnd = new Date(period.display_end);
        } else if (period.start && period.end) {
            // Fallback: Compute display boundaries from period.start/end (matches PeriodBoundaryContract logic)
            periodStart = new Date(period.start);
            periodStart.setHours(12, 0, 0, 0); // Normalize to 12:00:00
            periodEnd = new Date(period.end);
            periodEnd.setDate(periodEnd.getDate() - 1); // display_end = period.end - 1 day
            periodEnd.setHours(12, 0, 0, 0); // Normalize to 12:00:00
        } else {
            // No period boundaries available - cannot populate dropdown
            console.warn('Period missing start/end dates - cannot populate date dropdown');
        }
        
        if (periodStart && periodEnd) {
            
            // Generate all dates in the period (matches JS version lines 1805-1815)
            const dates = [];
            const currentDate = new Date(periodStart);
            while (currentDate <= periodEnd) {
                dates.push(new Date(currentDate));
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            // Group dates by month for better organization
            const datesByMonth = {};
            dates.forEach(date => {
                const monthKey = date.toLocaleString('en-US', { month: 'long', year: 'numeric' });
                if (!datesByMonth[monthKey]) {
                    datesByMonth[monthKey] = [];
                }
                datesByMonth[monthKey].push(date);
            });
            
            // Get month keys in order
            const monthKeys = Object.keys(datesByMonth);
            
            // Build dropdown options grouped by month
            monthKeys.forEach((monthKey, monthIndex) => {
                // Add month header (disabled option)
                const monthHeader = document.createElement('option');
                monthHeader.value = '';
                monthHeader.textContent = `━━━ ${monthKey} ━━━`;
                monthHeader.disabled = true;
                monthHeader.style.fontWeight = 'bold';
                monthHeader.style.backgroundColor = '#f3f4f6';
                dateSelect.appendChild(monthHeader);
                
                // Determine color based on month position
                // First month: Black (#000000), Second month: Blue (#1e40af)
                const isFirstMonth = monthIndex === 0;
                const textColor = isFirstMonth ? '#000000' : '#1e40af'; // Black for first month, Blue for second month
                
                // Add dates for this month (ALL dates are selectable - no past date check, matches JS version)
                datesByMonth[monthKey].forEach(date => {
                    const dateStr = date.toISOString().slice(0, 10);
                    const day = date.getDate();
                    const daySuffix = day === 1 || day === 21 || day === 31 ? 'st' : 
                                    day === 2 || day === 22 ? 'nd' : 
                                    day === 3 || day === 23 ? 'rd' : 'th';
                    const formattedDate = `${day}${daySuffix} ${date.toLocaleString('en-US', { month: 'short' })} ${date.getFullYear()}`;
                    
                    const option = document.createElement('option');
                    option.value = dateStr;
                    option.textContent = formattedDate;
                    
                    // Apply month-based color to ALL dates (matches JS version - no past date restriction)
                    option.style.color = textColor;
                    option.style.fontWeight = '500';
                    
                    dateSelect.appendChild(option);
                });
            });
            
            // Set selected value if reading has a date
            if (reading.date) {
                const readingDateStr = typeof reading.date === 'string' 
                    ? reading.date.split(' ')[0] 
                    : new Date(reading.date).toISOString().slice(0, 10);
                dateSelect.value = readingDateStr;
            }
        }
        
        // Disable dropdown for CALCULATED (immutable) periods
        if (isImmutable) {
            dateSelect.disabled = true;
            dateSelect.style.backgroundColor = '#f3f4f6';
            dateSelect.style.cursor = 'not-allowed';
        }
        
        // Handle date selection
        dateSelect.onchange = (e) => {
            // Prevent changes to CALCULATED (immutable) periods
            if (isImmutable) {
                dateSelect.value = reading.date ? (typeof reading.date === 'string' ? reading.date.split(' ')[0] : new Date(reading.date).toISOString().slice(0, 10)) : '';
                return;
            }
            
            const selectedDateStr = e.target.value;
            if (!selectedDateStr) {
                reading.date = null;
                updateCalculateButtonState();
                return;
            }
            
            const selectedDate = new Date(selectedDateStr);
            selectedDate.setHours(12, 0, 0, 0); // Match backend time normalization
            
            // Use pipeline-defined display boundaries (never compute manually)
            if (!period.display_start || !period.display_end) {
                console.error('Period missing display_start or display_end - cannot validate date');
                alert('Period boundary data missing. Please refresh and try again.');
                dateSelect.value = reading.date ? (typeof reading.date === 'string' ? reading.date.split(' ')[0] : new Date(reading.date).toISOString().slice(0, 10)) : '';
                return;
            }
            
            const periodStart = new Date(period.display_start);
            const periodEnd = new Date(period.display_end);
            
            // Validate date is within period boundaries (inclusive on both ends)
            if (selectedDate < periodStart || selectedDate > periodEnd) {
                alert(`Date must be within the period range: ${formatDateRange(period.start, period.end)}.`);
                dateSelect.value = reading.date ? (typeof reading.date === 'string' ? reading.date.split(' ')[0] : new Date(reading.date).toISOString().slice(0, 10)) : '';
                return;
            }
            
            reading.date = selectedDateStr;
            updateCalculateButtonState();
        };
        
        // Value input
        const valueInput = document.createElement('input');
        valueInput.type = 'number';
        valueInput.className = 'input-number';
        valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
        valueInput.placeholder = 'Reading (L)';
        
        // Disable inputs for CALCULATED (immutable) periods
        if (isImmutable) {
            valueInput.disabled = true;
            valueInput.style.backgroundColor = '#f3f4f6';
            valueInput.style.cursor = 'not-allowed';
        }
        
        valueInput.onchange = (e) => {
            // Prevent changes to CALCULATED (immutable) periods
            if (isImmutable) {
                valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
                return;
            }
            
            reading.value = e.target.value ? parseFloat(e.target.value) : null;
            updateCalculateButtonState();
        };
        
        // Delete button - hide for CALCULATED (immutable) periods
        const deleteButton = isImmutable ? '' : `
            <button onclick="delete_reading(${active}, ${idx})" 
                    style="background:var(--red); color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">
                Delete
            </button>
        `;
        
        row.innerHTML = `
            <td></td>
            <td></td>
            <td>—</td>
            <td>${deleteButton}</td>
        `;
        
        row.cells[0].appendChild(dateSelect);
        row.cells[1].appendChild(valueInput);
        
        tbody.appendChild(row);
    });
    
    // If no readings, show message only for CALCULATED (immutable) periods
    if (readings.length === 0 && isImmutable) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--muted); padding:20px;">This period is CALCULATED (immutable). No readings can be added.</td></tr>`;
    }
    
    // Update Add Reading button state after rendering
    updateAddReadingButtonState();
}

/**
 * Update calculate button state
 */
function updateCalculateButtonState() {
    const btn = document.getElementById('calculate_btn');
    if (!btn) return;
    
    const hasPeriods = periods.length > 0;
    const hasReadings = periods.some(p => p.readings && p.readings.some(r => r.date && r.value !== null));
    
    btn.disabled = !(hasPeriods && hasReadings);
}

/**
 * Update Add Reading button state based on active period status
 */
function updateAddReadingButtonState() {
    const btn = document.querySelector('.action-row button[onclick*="add_reading"]');
    if (!btn) return;
    
    if (active === null || active < 0 || active >= periods.length) {
        btn.disabled = true;
        btn.title = 'No active period';
        return;
    }
    
    const period = periods[active];
    // DIRECTIVE COMPLIANCE: Mutability is derived from billing_state only
    // is_mutable = (billing_state === PROVISIONAL)
    const isImmutable = period.status === 'CALCULATED';
    
    if (isImmutable) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        btn.title = 'Cannot add readings to CALCULATED periods (immutable periods)';
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.title = 'Add a new reading to this period';
    }
}

// ==================== PHP CALCULATION ====================

/**
 * Calculate using PHP (ONLY calculation function - all logic server-side)
 */
async function calculate() {
    const calculateBtn = document.getElementById('calculate_btn');
    const errorDiv = document.getElementById('calculate_error');
    
    try {
        calculateBtn.disabled = true;
        calculateBtn.textContent = 'Calculating...';
        if (errorDiv) errorDiv.style.display = 'none';
        
        if (periods.length === 0) {
            throw new Error('Please add at least one period');
        }
        
        const billDay = parseInt(document.getElementById('bill_day')?.value || 15);
        const startMonth = document.getElementById('start_month')?.value || '';
        const tiers = currentTemplateTiers && currentTemplateTiers.length > 0 ? currentTemplateTiers : [];
        
        // Validate tiers are present
        if (!tiers || tiers.length === 0) {
            throw new Error('Please select a tariff template first');
        }
        
        // Format periods for PHP endpoint
        const periodsData = periods.map((p, idx) => {
            const readings = (p.readings || [])
                .filter(r => r.date && r.value !== null && r.value !== undefined)
                .map(r => ({
                    date: typeof r.date === 'string' ? r.date : new Date(r.date).toISOString().slice(0, 10),
                    value: parseFloat(r.value)
                }));
            
            return {
                index: idx,
                start: p.start ? (typeof p.start === 'string' ? p.start : new Date(p.start).toISOString().slice(0, 10)) : null,
                end: p.end ? (typeof p.end === 'string' ? p.end : new Date(p.end).toISOString().slice(0, 10)) : null,
                readings: readings
            };
        });
        
        // Get current date for testing transitions
        const currentDate = document.getElementById('current_date')?.value || null;
        
        console.log('PHP Calculate - Sending to server:', { 
            billDay, 
            startMonth, 
            currentDate,
            periods: periodsData.length, 
            tiers: tiers.length,
            tiers_structure: tiers.length > 0 ? Object.keys(tiers[0]) : [],
            tiers_sample: tiers.length > 0 ? tiers[0] : null
        });
        
        // Call PHP calculator endpoint
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/admin/billing-calculator/php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                bill_day: billDay,
                start_month: startMonth,
                tiers: tiers,
                periods: periodsData,
                current_date: currentDate
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('[CALC DEBUG] PHP Calculate - Result received:', result);
        console.log('[CALC DEBUG] Result keys:', Object.keys(result));
        
        // Extract periods from response structure: { success: true, data: { periods: [...] } }
        const resultPeriods = result.data?.periods || result.periods || [];
        console.log('[CALC DEBUG] Result.data exists:', !!result.data);
        console.log('[CALC DEBUG] Result.data.periods exists:', !!result.data?.periods);
        console.log('[CALC DEBUG] Result.periods exists (direct):', !!result.periods);
        console.log('[CALC DEBUG] Extracted periods:', resultPeriods);
        console.log('[CALC DEBUG] Extracted periods type:', Array.isArray(resultPeriods));
        console.log('[CALC DEBUG] Extracted periods length:', resultPeriods.length);
        if (resultPeriods.length > 0) {
            console.log('[CALC DEBUG] First period:', resultPeriods[0]);
        }
        
        // Update periods with PHP calculation results
        if (resultPeriods && Array.isArray(resultPeriods) && resultPeriods.length > 0) {
            console.log('[CALC DEBUG] Updating periods array, current length:', periods.length);
            resultPeriods.forEach((phpPeriod, idx) => {
                if (periods[idx]) {
                    console.log(`[CALC DEBUG] Merging period ${idx}:`, phpPeriod);
                    // Merge PHP results into existing period
                    Object.assign(periods[idx], phpPeriod);
                } else {
                    console.warn(`[CALC DEBUG] Period ${idx} doesn't exist in periods array`);
                }
            });
            console.log('[CALC DEBUG] Periods after merge:', periods);
        } else {
            console.error('[CALC DEBUG] ERROR: No periods found in result!', {
                hasData: !!result.data,
                hasDataPeriods: !!result.data?.periods,
                hasDirectPeriods: !!result.periods,
                resultStructure: result
            });
        }
        
        // Re-render UI with PHP results (use global periods array which has been merged)
        console.log('[CALC DEBUG] Calling renderPeriodsTable()...');
        renderPeriodsTable();
        console.log('[CALC DEBUG] Calling renderReadingsTable()...');
        renderReadingsTable(); // This also calls updateAddReadingButtonState
        console.log('[CALC DEBUG] Calling renderCalculationOutput()...');
        // Use global periods array which has been merged with PHP results
        renderCalculationOutput({ periods: periods });
        console.log('[CALC DEBUG] Calling updateDashboard()...');
        updateDashboard({ periods: periods });
        console.log('[CALC DEBUG] Calling updateAddReadingButtonState()...');
        updateAddReadingButtonState(); // Ensure button state is updated after calculation
        console.log('[CALC DEBUG] All render functions called');
        
    } catch (error) {
        console.error('PHP Calculation error:', error);
        if (errorDiv) {
            errorDiv.textContent = `PHP calculator error: ${error.message}`;
            errorDiv.style.display = 'block';
        }
    } finally {
        if (calculateBtn) {
            calculateBtn.disabled = false;
            calculateBtn.textContent = 'Calculate';
        }
    }
}

/**
 * Render calculation output from PHP results
 * This is a simplified renderer - can be enhanced to match full UI output
 */
function renderCalculationOutput(result) {
    console.log('[CALC DEBUG] renderCalculationOutput called with:', result);
    const container = document.getElementById('period_output_container');
    console.log('[CALC DEBUG] Container found:', !!container);
    console.log('[CALC DEBUG] result.periods exists:', !!result.periods);
    console.log('[CALC DEBUG] result.periods type:', Array.isArray(result.periods));
    console.log('[CALC DEBUG] result.periods length:', result.periods?.length);
    
    if (!container) {
        console.error('[CALC DEBUG] ERROR: period_output_container not found!');
        return;
    }
    
    if (!result.periods) {
        console.error('[CALC DEBUG] ERROR: result.periods is missing!');
        return;
    }
    
    // Always use full detailed renderer with reconciliation logic
    // Don't call render_calculation_output as it may bypass reconciliation
    console.log('[CALC DEBUG] Using full detailed renderer with reconciliation');
    
    if (!result.periods || result.periods.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:var(--muted); padding:20px;">No periods calculated</div>';
        return;
    }
    
    // Show only active period (matches JS version)
    if (active === null || !result.periods[active]) {
        container.innerHTML = '<div style="text-align:center; color:var(--muted); padding:20px;">Select a period to view calculation details</div>';
        return;
    }
    
    const p = result.periods[active];
    
    // Only render if period has usage calculated
    if (p.usage === null || p.usage === undefined) {
        container.innerHTML = '<div style="text-align:center; color:var(--muted); padding:20px;">Period not calculated yet. Add readings and click Calculate.</div>';
        return;
    }
    
    // Use PHP-provided calculated values (all calculations done server-side)
    const period_days = p.period_days || null;
    const totalCost = p.total_cost || 0;
    const tierBreakdown = p.tier_breakdown || [];
    
    // Build Period Calculation Output HTML using IDENTICAL structure and styling as JS version
    let html = '';
    
    // Filter valid readings (matching JS version line 2484)
    const validReadings = (p.readings || []).filter(r => r.date && r.value !== null && r.value !== undefined).sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Meter Readings Section (matches JS version lines 2486-2501)
    if (validReadings.length > 0) {
        html += `<div class="output-section">
            <div class="output-header readings" onclick="this.parentElement.classList.toggle('collapsed')">Meter Readings</div>
            <div class="output-content">
                <div class="output-grid">`;
        
        validReadings.forEach((r, i) => {
            html += `<div class="output-field">
                <div class="output-label">Reading ${i + 1}</div>
                <div class="output-value calculable-field" data-field="readings" title="Right-click for calculation details">${r.date} → ${formatNumber(r.value)} L</div>
            </div>`;
        });
        
        html += `</div></div></div>`;
    }
    
    // Period Header with Status (matches JS version lines 2503-2520)
    // Use display_status for UI (READING REQUIRED / PROVISIONAL / CALCULATED)
    // display_status is computed by backend based on: closing/usage/dailyUsage null → READING REQUIRED
    // DIRECTIVE COMPLIANCE: Only PROVISIONAL and CALCULATED are valid billing states
    // UNRESOLVED, ACTUAL, OPEN, CLOSED are forbidden states and must not be displayed
    const displayStatus = p.display_status || p.status || 'PROVISIONAL';
    // Normalize display status: map any forbidden states to PROVISIONAL
    const normalizedStatus = (displayStatus === 'PROVISIONAL' || displayStatus === 'CALCULATED') 
        ? displayStatus 
        : (displayStatus === 'READING REQUIRED' ? 'READING REQUIRED' : 'PROVISIONAL');
    const statusColor = normalizedStatus === 'READING REQUIRED' ? '#ef4444' : 
                       normalizedStatus === 'CALCULATED' ? '#3b82f6' : 
                       normalizedStatus === 'PROVISIONAL' ? '#f59e0b' : '#6b7280';
    // DIRECTIVE COMPLIANCE: is_closed is internal-only, not exposed to UI
    // Mutability is derived from billing_state: is_mutable = (billing_state === PROVISIONAL)
    // Do not display OPEN/CLOSED labels
    const usageValue = (p.usage !== null && p.usage !== undefined) ? formatNumber(p.usage) : '—';
    
    // Format opening reading (handle Period 1 which uses start_reading)
    const openingValue = active === 0 && p.start_reading !== null && p.start_reading !== undefined
        ? formatNumber(p.start_reading)
        : (p.opening !== null && p.opening !== undefined 
            ? formatNumber(p.opening) 
            : '—');
    
    // Format closing reading - SNAPSHOT IMMUTABILITY RULE
    // Historical periods (snapshots) MUST show only single, immutable value
    // Only current (last) period can be mutable if PROVISIONAL
    const isSnapshot = active < periods.length - 1 || 
                      p.status === 'CALCULATED' || 
                      p.status === 'ACTUAL';
    
    // DIRECTIVE COMPLIANCE: Use provisional_closing for display (calculated_closing only if reconciled)
    let closingDisplay = '—';
    const isReconciled = p.is_reconciled === true;
    const effectiveClosing = (isReconciled && p.calculated_closing !== null && p.calculated_closing !== undefined)
        ? p.calculated_closing
        : (p.provisional_closing ?? null);
    
    if (effectiveClosing !== null && effectiveClosing !== undefined) {
        const closingValue = formatNumber(effectiveClosing);
        // SNAPSHOT RULE: Historical periods show ONLY final value, no dual display
        if (isSnapshot) {
            closingDisplay = closingValue;
        } else {
            // Current period only: can show provisional if available
            if (p.original_provisional_usage !== null && p.original_provisional_usage !== undefined) {
                const baseReading = active === 0 ? (p.start_reading || 0) : (p.opening || 0);
                const provisionalClosing = baseReading + p.original_provisional_usage;
                closingDisplay = `${closingValue} (${formatNumber(provisionalClosing)})`;
            } else {
                closingDisplay = closingValue;
            }
        }
    }
    
    html += `<div class="output-section">
        <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')" style="display:flex; justify-content:flex-start; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:16px; font-weight:700; color:var(--text);">Period ${active + 1} -</div>
                <div style="font-size:14px; color:var(--muted); margin-top:2px;">${formatDateRange(p.start, p.end)}</div>
            </div>
            <div style="font-size:14px; font-weight:600; color:var(--text);">${usageValue} L</div>
            <div style="font-size:14px; color:var(--text);">
                Opening: <strong>${openingValue}</strong>
            </div>
            <div style="font-size:14px; color:var(--text);">
                Provisional Closing: <strong>${closingDisplay}</strong>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="padding:4px 12px; background:${statusColor}20; color:${statusColor}; border-radius:4px; font-size:12px; font-weight:600;">${normalizedStatus}</span>
            </div>
        </div>
        <div class="output-content">`;
    
    // Period Opening State Section (matches JS version lines 2522-2539)
    if (active === 0 && p.start_reading !== null && p.start_reading !== undefined) {
        html += `<div class="output-grid">
            <div class="output-field">
                <div class="output-label">Start Reading</div>
                <div class="output-value">${formatNumber(p.start_reading)} L</div>
            </div>
        </div>`;
    } else if (active > 0 && p.opening !== null && p.opening !== undefined) {
        html += `<div class="output-grid">
            <div class="output-field">
                <div class="output-label">Opening Reading</div>
                <div class="output-value">${formatNumber(p.opening)} L</div>
            </div>
        </div>`;
        
        html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">(Carried forward from Period ${active}'s Closing_Reading)</div>`;
    }
    
    html += `</div></div>`;
    
    // CO Reading (Closing Opening) Section (matches JS version lines 2543-2567)
    // DIRECTIVE COMPLIANCE: Use provisional_closing for display (calculated_closing only if reconciled)
    const isReconciledForCO = p.is_reconciled === true;
    const effectiveClosingForCO = (isReconciledForCO && p.calculated_closing !== null && p.calculated_closing !== undefined)
        ? p.calculated_closing
        : (p.provisional_closing ?? null);
    
    if (effectiveClosingForCO !== null && effectiveClosingForCO !== undefined) {
        html += `<div class="output-section">
            <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')">CO Reading (Closing Opening)</div>
            <div class="output-content">`;
        
        // SNAPSHOT IMMUTABILITY RULE: Historical periods show ONLY final value
        const isSnapshot = active < periods.length - 1 || 
                          p.status === 'CALCULATED' || 
                          p.status === 'ACTUAL';
        
        // Show closing reading - single value only for snapshots
        let closingDisplay = formatNumber(effectiveClosingForCO);
        // NO dual display for historical periods - they are immutable snapshots
        
        html += `<div class="output-grid">
            <div class="output-field">
                <div class="output-label">CLOSING_READING</div>
                <div class="output-value">${closingDisplay} L</div>
            </div>
        </div>`;
        
        // SNAPSHOT IMMUTABILITY RULE: No historical annotations for snapshots
        // Reconciliation differences are shown ONLY in current period, not in historical periods
        if (isSnapshot) {
            // Historical period - show only calculation method, no provisional references
            html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">${active === 0 ? '(Calculated from START_READING + Period_Total_Usage)' : '(Calculated from OPENING_READING + Period_Total_Usage)'}</div>`;
        } else {
            // Current period only - can show calculation method
            html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">${active === 0 ? '(Calculated from START_READING + Period_Total_Usage)' : '(Calculated from OPENING_READING + Period_Total_Usage)'}</div>`;
        }
        
        html += `</div></div>`;
    }
    
    // Usage Calculation Section (matches JS version lines 2569-2595)
    if (p.usage !== null && p.usage !== undefined) {
        const daily_usage = p.dailyUsage || (p.usage / (period_days || 1));
        
        html += `<div class="output-section">
            <div class="output-header usage" onclick="this.parentElement.classList.toggle('collapsed')">Usage Calculation</div>
            <div class="output-content">
                <div class="output-grid">
                    <div class="output-field">
                        <div class="output-label">Total Period Usage</div>
                        <div class="output-value calculable-field" data-field="period_usage" title="Right-click for calculation details">${formatNumber(p.usage)} L</div>
                    </div>
                    <div class="output-field">
                        <div class="output-label">Daily Usage</div>
                        <div class="output-value calculable-field" data-field="daily_usage" title="Right-click for calculation details">${formatNumber(daily_usage)} L/day</div>
                    </div>
                    <div class="output-field">
                        <div class="output-label">Provisional Closing Reading</div>
                        <div class="output-value calculable-field" data-field="closing_reading" title="Right-click for calculation details">${formatNumber(effectiveClosing)} L</div>
                    </div>
                    <div class="output-field">
                        <div class="output-label">Period Days</div>
                        <div class="output-value calculable-field" data-field="period_days" title="Right-click for calculation details">${period_days}</div>
                    </div>
                </div>
            </div>
        </div>`;
    
        // Sector Breakdown Section (Derived Data - Read-Only Display)
        // Sectors are derived automatically from meter readings and period boundaries
        if (p.sectors && p.sectors.length > 0) {
            html += `<div class="output-section">
                <div class="output-header sector" onclick="this.parentElement.classList.toggle('collapsed')">5️⃣ Sector Breakdown (Derived Data)</div>
                <div class="output-content">
                    <div style="font-size:13px; color:var(--muted); margin-bottom:12px; padding:10px; background:rgba(59, 130, 246, 0.1); border-left:3px solid #3b82f6; border-radius:4px;">
                        <strong>Note:</strong> Sectors are derived automatically from meter readings and period boundaries and cannot be edited directly.
                    </div>`;
            
            p.sectors.forEach(s => {
                // Use sub_id if available (for sectors that cross period boundaries), otherwise use sector_id
                // Clearly label as "Sector 1", "Sector 2", "Sector 3a", etc.
                const sector_label = (s.sub_id != null && typeof s.sub_id === 'string' && s.sub_id !== 'NaN') 
                    ? `Sector ${s.sub_id}` 
                    : `Sector ${s.sector_id}`;
                
                // Get the usage value (could be total_usage, usage_in_period, or sector_usage)
                const total_usage = s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0;
                // Get the days value (could be days_in_period or sector_days)
                const days_in_period = s.days_in_period ?? s.sector_days ?? 0;
                // Get the daily usage value
                const daily_usage = s.daily_usage ?? s.sector_daily_usage ?? 0;
                
                // Format date for display (matches JS version iso() function)
                const formatSectorDate = (dateStr) => {
                    if (!dateStr) return '—';
                    const d = new Date(dateStr);
                    if (isNaN(d.getTime())) return '—';
                    return d.toISOString().split('T')[0];
                };
                
                // Sector label displayed OUTSIDE the card, before the details (like JS version)
                html += `<div class="sector-item">
                    <div class="sector-label">${sector_label}</div>
                    <div class="output-grid">`;
                html += `<div class="output-field"><div class="output-label">Start Date</div><div class="output-value">${formatSectorDate(s.start_date)}</div></div>`;
                html += `<div class="output-field"><div class="output-label">End Date</div><div class="output-value">${formatSectorDate(s.end_date)}</div></div>`;
                html += `<div class="output-field"><div class="output-label">Start Reading</div><div class="output-value">${(s.start_reading ?? 0).toFixed(0)} L</div></div>`;
                html += `<div class="output-field"><div class="output-label">End Reading</div><div class="output-value">${(s.end_reading ?? 0).toFixed(0)} L</div></div>`;
                html += `<div class="output-field"><div class="output-label">Total Usage</div><div class="output-value">${total_usage.toFixed(0)} L</div></div>`;
                html += `<div class="output-field"><div class="output-label">Daily Usage</div><div class="output-value">${daily_usage.toFixed(2)} L/day</div></div>`;
                html += `<div class="output-field"><div class="output-label">Days in Period</div><div class="output-value">${days_in_period} days</div></div>`;
                html += `</div></div>`;
            });
            
            // Validation (matches JS version lines 2631-2667)
            let total_sector_days = 0;
            let total_sector_usage = 0;
            p.sectors.forEach(s => {
                total_sector_days += (s.days_in_period ?? s.sector_days ?? 0);
                total_sector_usage += (s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0);
            });
            
            const weighted_sector_daily_usage = total_sector_days > 0 ? total_sector_usage / total_sector_days : 0;
            const period_daily_usage = p.dailyUsage || (p.usage / (period_days || 1));
            const daily_usage_match = Math.abs(weighted_sector_daily_usage - period_daily_usage) < 0.01;
            
            let validation_note = "";
            if (p.status === 'ACTUAL') {
                validation_note = ' (Sectors validate ACTUAL period - calculation not overridden)';
            } else if (p.status === 'CALCULATED') {
                validation_note = ' (Sectors used for recalculation from PROVISIONAL to CALCULATED)';
            } else {
                validation_note = ' (Sectors validate PROVISIONAL period - only daily_usage is validated)';
            }
            
            if (daily_usage_match) {
                if (p.status === 'PROVISIONAL') {
                    html += `<div class="validation-success">✓ Validation Passed: Daily Usage matches (${weighted_sector_daily_usage.toFixed(2)} L/day = ${period_daily_usage.toFixed(2)} L/day). Sector days (${total_sector_days}) and usage (${total_sector_usage.toFixed(0)} L) are actual values, period values are projected${validation_note}</div>`;
                } else {
                    html += `<div class="validation-success">✓ Validation Passed: Sum of sector days (${total_sector_days}) = Period Days (${period_days}), Sum of sector usage (${total_sector_usage.toFixed(0)} L) = Period Total Usage (${p.usage.toFixed(0)} L)${validation_note}</div>`;
                }
            } else {
                const failures = [];
                if (p.status !== 'PROVISIONAL') {
                    if (total_sector_days !== period_days) failures.push(`Days match: false (${total_sector_days} vs ${period_days})`);
                    if (Math.abs(total_sector_usage - p.usage) > 0.01) failures.push(`Usage match: false (${total_sector_usage.toFixed(0)} vs ${p.usage.toFixed(0)})`);
                }
                if (!daily_usage_match) failures.push(`Daily Usage match: false (${weighted_sector_daily_usage.toFixed(2)} vs ${period_daily_usage.toFixed(2)})`);
                
                html += `<div class="validation-error">⚠ Validation Failed: ${failures.join(', ')}${validation_note}</div>`;
            }
            
            html += `</div></div>`;
        }
        
        // Calculate tier charges (matches JS version lines 2672-2689)
        const tier_items = [];
        if (tierBreakdown && tierBreakdown.length > 0) {
            tierBreakdown.forEach(item => {
                if (item.used > 0) {
                    tier_items.push({
                        prev: item.prev || 0,
                        max: item.max || Infinity,
                        used: item.used,
                        rate: item.rate,
                        cost: item.cost
                    });
                }
            });
        }
        
        // Tier Charges Section (Collapsible) (matches JS version lines 2693-2710)
        if (tier_items.length > 0) {
            html += `<div class="output-section collapsed">
                <div class="output-header tier" onclick="this.parentElement.classList.toggle('collapsed')">
                    Tier Charges
                </div>
                <div class="output-content">
                    <div class="output-grid">`;
            
            tier_items.forEach(item => {
                html += `<div class="output-field">
                    <div class="output-label">Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L</div>
                    <div class="output-value">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>
                </div>`;
            });
            
            html += `</div></div></div>`;
        }
    
    // Reconciliation Tier Cost Section - Show ALL consecutive recalculated periods
    // Only shown in the period where the reading was done (immediately after recalculated periods)
    // Matches JS version lines 2733-2846
    if (active !== null && active > 0) {
        // Find consecutive recalculated periods ending just before the current period
        const consecutive_recalculated = [];
        for (let i = active - 1; i >= 0; i--) {
            const check_period = result.periods[i];
            console.log(`[RECON DEBUG] Checking period ${i}:`, {
                status: check_period.status,
                is_closed: check_period.is_closed,
                has_original_provisional: check_period.original_provisional_usage !== null && check_period.original_provisional_usage !== undefined,
                has_usage: check_period.usage !== null && check_period.usage !== undefined,
                original_provisional: check_period.original_provisional_usage,
                usage: check_period.usage
            });
            // DIRECTIVE COMPLIANCE: is_closed is internal-only, not used for UI logic
            // Mutability is derived from billing_state: is_mutable = (billing_state === PROVISIONAL)
            if (check_period.status === 'CALCULATED' && 
                check_period.original_provisional_usage !== null && 
                check_period.original_provisional_usage !== undefined &&
                check_period.usage !== null && 
                check_period.usage !== undefined) {
                consecutive_recalculated.unshift(i); // Add to front to maintain order
                console.log(`[RECON DEBUG] Period ${i} added to consecutive_recalculated`);
            } else {
                // Stop if we hit a non-recalculated period (not consecutive)
                console.log(`[RECON DEBUG] Stopping at period ${i} - not consecutive CALCULATED`);
                break;
            }
        }
        
        console.log(`[RECON DEBUG] consecutive_recalculated:`, consecutive_recalculated);
        console.log(`[RECON DEBUG] active:`, active);
        console.log(`[RECON DEBUG] p.status:`, p.status);
        console.log(`[RECON DEBUG] Condition check:`, {
            has_recalculated: consecutive_recalculated.length > 0,
            is_next_period: active === (consecutive_recalculated.length > 0 ? consecutive_recalculated[consecutive_recalculated.length - 1] + 1 : -1),
            is_not_calculated: p.status !== 'CALCULATED'
        });
        
        // Only show reconciliation in the period immediately after the consecutive recalculated periods
        // AND only if the current period is NOT CALCULATED (it's PROVISIONAL or ACTUAL)
        if (consecutive_recalculated.length > 0 && 
            active === consecutive_recalculated[consecutive_recalculated.length - 1] + 1 &&
            p.status !== 'CALCULATED') {
            
            console.log(`[RECON DEBUG] Showing reconciliation for periods:`, consecutive_recalculated);
            consecutive_recalculated.forEach(period_idx => {
                const check_period = result.periods[period_idx];
                
                // Use PHP-provided reconciliation data (all calculations done server-side)
                const reconciliation_cost = check_period.reconciliation || null;
                
                if (reconciliation_cost !== null) {
                    const period_num = period_idx + 1;
                    html += `<div style="margin-bottom:24px; padding:16px; background:var(--card); border-radius:8px; border:1px solid var(--border);">`;
                    html += `<h3 style="font-size:16px; font-weight:700; margin-bottom:12px; color:var(--text);">Reconciliation (Period ${period_num})</h3>`;
                    html += `<div style="font-size:12px; color:var(--muted); margin-bottom:12px;">`;
                    html += `Reconciliation for Period ${period_num} displayed in Period ${active + 1}`;
                    html += `</div>`;
                    
                    // Use PHP-provided adjustment_litres (no calculation in frontend)
                    const adjustment_litres = reconciliation_cost.adjustment_litres || 0;
                    
                    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">`;
                    html += `<div>`;
                    html += `<div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Reconciliation Amount</div>`;
                    html += `<div style="font-size:16px; font-weight:700; color:${adjustment_litres >= 0 ? '#dc3545' : '#28a745'};">`;
                    html += `${adjustment_litres >= 0 ? '+' : ''}${formatNumber(adjustment_litres)} L`;
                    html += `</div>`;
                    html += `</div>`;
                    html += `<div>`;
                    html += `<div style="font-size:14px; color:var(--muted); margin-bottom:4px;">Reconciliation Cost</div>`;
                    html += `<div style="font-size:16px; font-weight:700; color:${reconciliation_cost.total_cost >= 0 ? '#dc3545' : '#28a745'};">`;
                    html += `${reconciliation_cost.total_cost >= 0 ? '+' : ''}R ${Math.abs(reconciliation_cost.total_cost).toFixed(2)}`;
                    html += `</div>`;
                    html += `</div>`;
                    html += `</div>`;
                    
                    html += `<div style="font-size:11px; color:var(--muted); margin-bottom:8px;">`;
                    html += `(CALCULATED Usage ${reconciliation_cost.calculated_litres.toFixed(0)} L - PROVISIONED Usage ${reconciliation_cost.provisioned_litres.toFixed(0)} L)`;
                    html += `</div>`;
                    
                    // PROVISIONED Usage section (matches JS line 2799-2812)
                    html += `<div style="margin-bottom:16px; padding:12px; background:rgba(255, 193, 7, 0.1); border-left:3px solid #ffc107; border-radius:4px;">`;
                    html += `<div style="font-weight:600; margin-bottom:8px; color:#856404;">1️⃣ PROVISIONED Usage (${reconciliation_cost.provisioned_litres.toFixed(0)} L) - Original Bill (To Credit):</div>`;
                    if (reconciliation_cost.provisioned_breakdown.length > 0) {
                        reconciliation_cost.provisioned_breakdown.forEach((item) => {
                            html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">`;
                            // Match JS format: Tier ${item.prev}–${formatTierMax(item.max)} L
                            html += `<div style="font-size:14px;">Tier ${item.prev}–${formatTierMax(item.max)} L</div>`;
                            html += `<div style="text-align:right; font-size:14px;">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>`;
                            html += `</div>`;
                        });
                    } else {
                        html += `<div style="color:var(--muted);">No tier allocation (0 L)</div>`;
                    }
                    html += `<div style="margin-top:8px; font-weight:600;">Total Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}</div>`;
                    html += `</div>`;
                    
                    // CALCULATED Usage section (matches JS line 2814-2827)
                    html += `<div style="margin-bottom:16px; padding:12px; background:rgba(40, 167, 69, 0.1); border-left:3px solid #28a745; border-radius:4px;">`;
                    html += `<div style="font-weight:600; margin-bottom:8px; color:#155724;">2️⃣ CALCULATED Usage (${reconciliation_cost.calculated_litres.toFixed(0)} L) - Corrected Bill (To Charge):</div>`;
                    if (reconciliation_cost.calculated_breakdown.length > 0) {
                        reconciliation_cost.calculated_breakdown.forEach((item) => {
                            html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">`;
                            // Match JS format: Tier ${item.prev}–${formatTierMax(item.max)} L
                            html += `<div style="font-size:14px;">Tier ${item.prev}–${formatTierMax(item.max)} L</div>`;
                            html += `<div style="text-align:right; font-size:14px;">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>`;
                            html += `</div>`;
                        });
                    } else {
                        html += `<div style="color:var(--muted);">No tier allocation (0 L)</div>`;
                    }
                    html += `<div style="margin-top:8px; font-weight:600;">Total Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}</div>`;
                    html += `</div>`;
                    
                    // Reconciliation Cost Calculation section
                    html += `<div style="padding:12px; background:rgba(0, 123, 255, 0.1); border-left:3px solid #007bff; border-radius:4px;">`;
                    html += `<div style="font-weight:600; margin-bottom:8px; color:#004085;">3️⃣ Reconciliation Cost Calculation:</div>`;
                    html += `<div style="font-size:13px; line-height:1.6;">`;
                    html += `<div>Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}</div>`;
                    html += `<div>Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}</div>`;
                    html += `<div style="margin-top:4px; font-weight:600;">`;
                    html += `Reconciliation Cost = R${reconciliation_cost.calculated_cost.toFixed(2)} - R${reconciliation_cost.provisioned_cost.toFixed(2)} = `;
                    html += `<span style="color:${reconciliation_cost.total_cost >= 0 ? '#dc3545' : '#28a745'};">
                        ${reconciliation_cost.total_cost >= 0 ? 'R' : '-R'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}
                    </span>`;
                    html += `</div>`;
                    html += `</div>`;
                    html += `</div>`;
                    html += `</div>`;
                }
            });
        }
    }
    
    } else {
        html += `<div class="output-section">
            <div class="output-header usage" onclick="this.parentElement.classList.toggle('collapsed')">Usage Calculation</div>
            <div class="output-content">
                <div class="validation-warning">Not calculated (need at least 2 readings)</div>
            </div>
        </div>`;
    }
    
    // Cost Summary Section (matches JS version lines 2828-2843)
    html += `<div class="output-section">
        <div class="output-header cost" onclick="this.parentElement.classList.toggle('collapsed')">Cost Summary</div>
        <div class="output-content">
            <div class="output-grid">
                <div class="output-field">
                    <div class="output-label">Total Cost</div>
                    <div class="output-value total">R ${totalCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")}</div>
                </div>
                <div class="output-field">
                    <div class="output-label">Average Daily Cost</div>
                    <div class="output-value">R ${(p.daily_cost || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")} / day</div>
                </div>
            </div>
        </div>
    </div>`;
    
    console.log('[CALC DEBUG] Setting container.innerHTML, length:', html.length);
    container.innerHTML = html;
    console.log('[CALC DEBUG] Container updated, innerHTML length:', container.innerHTML.length);
}

// Export render function for use by inline scripts
window.render_calculation_output = function() {
    console.log('[CALC DEBUG] render_calculation_output called');
    // Re-render using current periods state
    // Note: periods is a global variable in this file
    console.log('[CALC DEBUG] Current periods:', periods);
    console.log('[CALC DEBUG] Periods length:', periods?.length);
    if (periods && periods.length > 0) {
        console.log('[CALC DEBUG] Calling renderCalculationOutput with periods');
        renderCalculationOutput({ periods: periods });
    } else {
        console.warn('[CALC DEBUG] No periods to render');
    }
};

/**
 * Update dashboard metrics
 * Matches JS version exactly: calculates costs from tiers, rounds daily usage
 */
function updateDashboard(result) {
    if (!result.periods || result.periods.length === 0) return;
    
    // Get active period index
    const activePeriodIndex = active !== null && active >= 0 ? active : null;
    
    if (activePeriodIndex === null || activePeriodIndex >= result.periods.length) {
        // No active period - show dashes
        const dailyUsageEl = document.getElementById('period_dashboard_daily_usage');
        const dailyCostEl = document.getElementById('period_dashboard_daily_cost');
        const totalUsedEl = document.getElementById('period_dashboard_total_used');
        const totalCostEl = document.getElementById('period_dashboard_total_cost');
        const usageLabelEl = document.getElementById('period_dashboard_total_used_label');
        const costLabelEl = document.getElementById('period_dashboard_total_cost_label');
        
        if (dailyUsageEl) dailyUsageEl.textContent = '—';
        if (dailyCostEl) dailyCostEl.textContent = '—';
        if (totalUsedEl) totalUsedEl.textContent = '—';
        if (totalCostEl) totalCostEl.textContent = 'R 0.00';
        if (usageLabelEl) usageLabelEl.textContent = 'Total Used';
        if (costLabelEl) costLabelEl.textContent = 'Total Cost';
        return;
    }
    
    // Get ONLY the active period (matches JS version line 1222-1223)
    const p = result.periods[activePeriodIndex];
    
    // Determine status labels based on period index and status
    let usageLabel = 'Total Used';
    let costLabel = 'Projected Total'; // Default to Projected Total for mobile design
    
    // Mobile design: Show "Projected Total" for provisional, "Total Cost" for calculated/actual
    if (p.status === 'ACTUAL' || p.status === 'CALCULATED') {
        costLabel = 'Total Cost';
    } else {
        costLabel = 'Projected Total';
    }
    
    // Update status labels
    const usageLabelEl = document.getElementById('period_dashboard_total_used_label');
    const costLabelEl = document.getElementById('period_dashboard_total_cost_label');
    if (usageLabelEl) usageLabelEl.textContent = usageLabel;
    if (costLabelEl) costLabelEl.textContent = costLabel;
    
    // Use PHP-provided calculated values (all calculations done server-side)
    const period_days = p.period_days || null;
    const totalUsage = p.usage || 0;
    const dailyUsage = p.dailyUsage || 0;
    const totalCost = p.total_cost || 0;
    const dailyCost = p.daily_cost || 0;
    
    // Round daily usage to nearest litre (matches JS version)
    const roundedDailyUsage = Math.round(dailyUsage);
    
    // Update dashboard elements (matches JS version formatting)
    const dailyUsageEl = document.getElementById('period_dashboard_daily_usage');
    const dailyCostEl = document.getElementById('period_dashboard_daily_cost');
    const totalUsedEl = document.getElementById('period_dashboard_total_used');
    const totalCostEl = document.getElementById('period_dashboard_total_cost');
    
    if (dailyUsageEl) {
        dailyUsageEl.textContent = totalUsage > 0 ? formatNumber(roundedDailyUsage) + ' L' : '—';
    }
    if (dailyCostEl) {
        // Always display daily cost (even if 0) - PHP always provides this value
        if (dailyCost !== null && dailyCost !== undefined) {
            dailyCostEl.textContent = 'R ' + dailyCost.toFixed(2);
        } else {
            dailyCostEl.textContent = '—';
        }
    }
    if (totalUsedEl) {
        totalUsedEl.textContent = totalUsage > 0 ? formatNumber(totalUsage) + ' L' : '—';
    }
    if (totalCostEl) {
        // Always display total cost (even if 0) - PHP always provides this value
        if (totalCost !== null && totalCost !== undefined) {
            const formattedCost = totalCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            totalCostEl.textContent = 'R ' + formattedCost;
        } else {
            totalCostEl.textContent = 'R 0.00';
        }
    }
}

// ==================== EXPORT TO GLOBAL SCOPE ====================

window.add_period = add_period;
window.add_reading = add_reading;
window.delete_reading = delete_reading;
window.delete_period = delete_period;
window.calculate = calculate;

// Expose periods for debugging
window.getPeriods = () => periods;
window.setPeriods = (newPeriods) => {
    periods = newPeriods;
    renderPeriodsTable();
    renderReadingsTable(); // This also calls updateAddReadingButtonState
    updateAddReadingButtonState(); // Ensure button state is updated
};

// ==================== DATE-TO-DATE (SECTOR) CALCULATIONS ====================

// Global state for sectors (date-to-date mode)
let sectors = [];
let activeSector = null;

/**
 * Calculate sector using PHP (date-to-date mode)
 */
async function calculate_sector() {
    const calculateBtn = document.getElementById('sector_calculate_btn');
    const errorDiv = document.getElementById('sector_calculate_error');
    
    try {
        if (calculateBtn) {
            calculateBtn.disabled = true;
            calculateBtn.textContent = 'Calculating...';
        }
        if (errorDiv) errorDiv.style.display = 'none';
        
        // Get sectors data from UI (if available from SectorBillingLogic)
        let sectorsData = [];
        if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.getSectors) {
            sectorsData = SectorBillingLogic.getSectors();
        } else {
            // Fallback: use local sectors state
            sectorsData = sectors;
        }
        
        if (sectorsData.length === 0) {
            throw new Error('Please add at least one sector with readings');
        }
        
        // Get tiers
        const tiers = currentTemplateTiers && currentTemplateTiers.length > 0 ? currentTemplateTiers : [];
        
        if (tiers.length === 0) {
            throw new Error('Please select a tariff template');
        }
        
        // Get selected date if available
        const selectedDate = document.getElementById('sector_date_picker')?.value || null;
        
        console.log('PHP Sector Calculate - Sending to server:', { sectors: sectorsData.length, tiers: tiers.length, selectedDate });
        
        // Call PHP sector calculator endpoint
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/admin/billing-calculator/php/sector', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                tiers: tiers,
                sectors: sectorsData,
                selected_date: selectedDate
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('PHP Sector Calculate - Result received:', result);
        
        // Update sectors with PHP results
        if (result.sectors && Array.isArray(result.sectors)) {
            sectors = result.sectors;
            
            // Update SectorBillingLogic if available
            if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.setSectors) {
                SectorBillingLogic.setSectors(result.sectors);
            }
            
            // Set active sector
            if (result.active_sector !== null && result.active_sector !== undefined) {
                activeSector = result.active_sector;
                if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.setActiveSector) {
                    SectorBillingLogic.setActiveSector(result.active_sector);
                }
            }
        }
        
        // Update sector_date_picker min constraint (DATE TO DATE: cannot go earlier, only forward)
        updateSectorDatePickerConstraint();
        
        // Re-render UI with PHP results
        if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
            SectorBillingUI.render();
        } else if (typeof render_sector_output === 'function') {
            render_sector_output(result);
        }
        
    } catch (error) {
        console.error('PHP Sector Calculation error:', error);
        if (errorDiv) {
            errorDiv.textContent = `PHP calculator error: ${error.message}`;
            errorDiv.style.display = 'block';
        }
    } finally {
        if (calculateBtn) {
            calculateBtn.disabled = false;
            calculateBtn.textContent = 'Calculate';
        }
    }
}

// Export calculate_sector for global access
window.calculate_sector = calculate_sector;

/**
 * Update sector_date_picker min constraint based on last reading date
 * DATE TO DATE: Cannot go earlier - only forward dates allowed
 */
function updateSectorDatePickerConstraint() {
    const datePicker = document.getElementById('sector_date_picker');
    if (!datePicker) return;
    
    // Get all sectors and find the latest reading date
    let latestReadingDate = null;
    
    // Try to get sectors from SectorBillingLogic if available
    if (typeof SectorBillingLogic !== 'undefined' && SectorBillingLogic.getSectors) {
        const allSectors = SectorBillingLogic.getSectors();
        allSectors.forEach(sector => {
            if (sector.readings && sector.readings.length > 0) {
                sector.readings.forEach(reading => {
                    if (reading.date) {
                        const readingDate = new Date(reading.date);
                        if (!latestReadingDate || readingDate > latestReadingDate) {
                            latestReadingDate = readingDate;
                        }
                    }
                });
            }
        });
    } else if (typeof sectors !== 'undefined' && Array.isArray(sectors)) {
        // Fallback: use local sectors variable
        sectors.forEach(sector => {
            if (sector.readings && sector.readings.length > 0) {
                sector.readings.forEach(reading => {
                    if (reading.date) {
                        const readingDate = new Date(reading.date);
                        if (!latestReadingDate || readingDate > latestReadingDate) {
                            latestReadingDate = readingDate;
                        }
                    }
                });
            }
        });
    }
    
    // Set min date to day after latest reading (or today if no readings)
    if (latestReadingDate) {
        const minDate = new Date(latestReadingDate);
        minDate.setDate(minDate.getDate() + 1); // Day after last reading
        datePicker.min = minDate.toISOString().slice(0, 10);
    } else {
        // No readings yet - allow any date from today onwards
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        datePicker.min = today.toISOString().slice(0, 10);
    }
}

// Export for global access
window.updateSectorDatePickerConstraint = updateSectorDatePickerConstraint;

// Initialize UI on load
document.addEventListener('DOMContentLoaded', () => {
    renderPeriodsTable();
    renderReadingsTable();
    updateCalculateButtonState();
    updateSectorDatePickerConstraint();
});

