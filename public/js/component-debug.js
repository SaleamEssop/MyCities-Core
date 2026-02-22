/**
 * Component Debug Information System for Laravel Blade Pages
 * Right-click any component to see debug information
 */

(function() {
    'use strict';

    // Component registry - defines what each component does
    const componentRegistry = {
        'dashboard-header': {
            name: 'Dashboard Header',
            description: 'Displays the MyCities logo, user name, and dashboard label. Shows the total amount and period navigation arrows.',
            purpose: 'Header section providing branding and period navigation',
            dataSource: 'dashboardData.account.name, dashboardData.totals.grand_total, dashboardData.period',
            outputs: 'Period navigation links, total amount display'
        },
        'period-navigation': {
            name: 'Period Navigation',
            description: 'Allows navigation between billing periods using left/right arrows. Shows current period index and enables/disables navigation based on available periods.',
            purpose: 'Navigate between current and past billing periods',
            dataSource: 'currentPeriodIndex, allPeriods array',
            outputs: 'URL links to previous/next periods, period date display'
        },
        'water-meter-section': {
            name: 'Water Meter Section',
            description: 'Displays water meter statistics (daily usage, total usage, total cost), meter number, closing reading, and detailed billing breakdown including tiers, sewerage, fixed costs, and VAT.',
            purpose: 'Show water consumption and charges breakdown',
            dataSource: 'dashboardData.water (consumption, charges.breakdown, totals, closing_reading)',
            outputs: 'Water consumption stats, tier breakdown, consumption total, VAT, period total'
        },
        'electricity-meter-section': {
            name: 'Electricity Meter Section',
            description: 'Displays electricity meter statistics, consumption, charges breakdown, and period totals.',
            purpose: 'Show electricity consumption and charges breakdown',
            dataSource: 'dashboardData.electricity (consumption, charges.breakdown, totals)',
            outputs: 'Electricity consumption stats, tier breakdown, consumption total, VAT, period total'
        },
        'tier-breakdown': {
            name: 'Tier Breakdown',
            description: 'Shows individual tier charges for water consumption. Each tier displays the tier label and charge amount. Tiers are calculated based on consumption ranges defined in the tariff template.',
            purpose: 'Display detailed tier-by-tier water charges',
            dataSource: 'dashboardData.water.charges.breakdown (filtered by type=water_in)',
            outputs: 'Individual tier charges that sum to consumption total'
        },
        'water-out-charge': {
            name: 'Water Out (Sewerage) Charge',
            description: 'Displays sewerage charges calculated as a percentage of water consumption charges.',
            purpose: 'Show sewerage/discharge charges',
            dataSource: 'dashboardData.water.charges.breakdown (filtered by type=water_out)',
            outputs: 'Sewerage charge amount added to consumption total'
        },
        'additional-charges': {
            name: 'Additional Charges',
            description: 'Shows any additional charges beyond standard consumption and sewerage (e.g., infrastructure surcharges, special fees).',
            purpose: 'Display additional fees and surcharges',
            dataSource: 'dashboardData.water.charges.breakdown (filtered by type=additional)',
            outputs: 'Additional charge amounts added to consumption total'
        },
        'fixed-costs': {
            name: 'Fixed Costs',
            description: 'Displays fixed monthly charges that are not based on consumption (e.g., service fees, connection fees).',
            purpose: 'Show fixed monthly charges',
            dataSource: 'dashboardData.water.charges.breakdown (filtered by type=fixed)',
            outputs: 'Fixed cost amounts added to total charges'
        },
        'customer-costs': {
            name: 'Customer Costs',
            description: 'Shows account-specific costs that are added to the bill (e.g., account-level fees, custom charges).',
            purpose: 'Display account-specific charges',
            dataSource: 'dashboardData.water.charges.breakdown (filtered by type=customer)',
            outputs: 'Customer cost amounts added to total charges'
        },
        'consumption-total': {
            name: 'Consumption Total',
            description: 'Sum of all consumption-related charges (tiers, sewerage, additional). This is the base amount before VAT.',
            purpose: 'Show total consumption charges',
            dataSource: 'dashboardData.water.charges.total',
            outputs: 'Total consumption charge (used for VAT calculation)'
        },
        'vat-amount': {
            name: 'VAT Amount',
            description: 'Value Added Tax calculated as a percentage of consumption total. Only shown if VAT amount > 0.',
            purpose: 'Display VAT on consumption',
            dataSource: 'dashboardData.water.totals.vat_amount, dashboardData.water.totals.vat_rate',
            outputs: 'VAT amount added to period total'
        },
        'period-total': {
            name: 'Period Total',
            description: 'Final total for the meter type (consumption total + VAT). This is the amount charged for this meter in this period.',
            purpose: 'Show final charge for meter type',
            dataSource: 'dashboardData.water.totals.period_total',
            outputs: 'Period total for water/electricity (contributes to grand total)'
        },
        'grand-total': {
            name: 'Grand Total',
            description: 'Sum of all period totals (water + electricity) for the current billing period. This is the total amount due.',
            purpose: 'Show total amount due for period',
            dataSource: 'dashboardData.totals.grand_total',
            outputs: 'Total amount due (displayed in header)'
        },
        'readings-section': {
            name: 'Readings Section',
            description: 'Displays meter readings entry interface with water and electricity meter inputs, reading history, and numeric keypad.',
            purpose: 'Enter and view meter readings',
            dataSource: 'meterData.water.readings, meterData.electricity.readings, periodInfo',
            outputs: 'New meter readings submitted via form (adds to readings array)'
        },
        'water-reading-input': {
            name: 'Water Reading Input',
            description: 'Allows entry of water meter reading in kiloliters-liters format (00000-00). Calculates total liters for submission.',
            purpose: 'Enter water meter reading',
            dataSource: 'User input (kiloliters + liters)',
            outputs: 'reading_value (total liters) submitted to add-reading endpoint'
        },
        'electricity-reading-input': {
            name: 'Electricity Reading Input',
            description: 'Allows entry of electricity meter reading in kWh. Displays as 6-digit number.',
            purpose: 'Enter electricity meter reading',
            dataSource: 'User input (kWh)',
            outputs: 'reading_value (kWh) submitted to add-reading endpoint'
        },
        'reading-history': {
            name: 'Reading History',
            description: 'Shows all readings entered for the current billing period. Displays date and value for each reading.',
            purpose: 'Display readings for current period',
            dataSource: 'meterData.water.readings, meterData.electricity.readings',
            outputs: 'List of readings (used to calculate consumption)'
        },
        'payment-entry': {
            name: 'Payment Entry',
            description: 'Allows entry of payment amounts. Defaults to total owing. Updates account balance when submitted.',
            purpose: 'Record payments against account',
            dataSource: 'accountData.total_owing, user input',
            outputs: 'Payment record added to account (reduces balance)'
        },
        'billing-periods': {
            name: 'Billing Periods List',
            description: 'Shows all billing periods with consumption charges, balance brought forward, payments, and final balance for each period.',
            purpose: 'Display billing history and account balance',
            dataSource: 'accountData.periods array',
            outputs: 'Period cards showing balance progression'
        },
        'period-card': {
            name: 'Period Card',
            description: 'Displays a single billing period with dates, consumption charge, balance B/F, payments, and final balance.',
            purpose: 'Show details for one billing period',
            dataSource: 'period object (start_date, end_date, consumption_charge, balance_bf, payments, balance)',
            outputs: 'Period information display'
        },
        'bottom-navigation': {
            name: 'Bottom Navigation',
            description: 'Fixed navigation bar at bottom of page with links to Home, Dashboard, Readings, and Accounts pages.',
            purpose: 'Navigate between main pages',
            dataSource: 'Route definitions',
            outputs: 'Navigation links to other pages'
        }
    };

    // Initialize component debug on page load
    function initComponentDebug() {
        // Add data attributes to all major components
        addComponentIdentifiers();

        // Listen for right-click events
        document.addEventListener('contextmenu', handleRightClick, true);
    }

    // Add data-component attributes to identify components
    function addComponentIdentifiers() {
        // Dashboard components
        const dashboardHeader = document.querySelector('.dashboard-header, .unified-header');
        if (dashboardHeader) {
            dashboardHeader.setAttribute('data-component', 'dashboard-header');
            dashboardHeader.setAttribute('data-component-id', 'dashboard-header-1');
        }

        const periodNav = document.querySelector('.total-amount-row, .period-navigation');
        if (periodNav) {
            periodNav.setAttribute('data-component', 'period-navigation');
            periodNav.setAttribute('data-component-id', 'period-navigation-1');
        }

        const waterSection = document.querySelector('.meter-section:has(.meter-type-header.water), .meter-section .meter-icon.fa-tint');
        if (waterSection) {
            waterSection.setAttribute('data-component', 'water-meter-section');
            waterSection.setAttribute('data-component-id', 'water-meter-section-1');
        }

        const electricitySection = document.querySelector('.meter-section:has(.meter-type-header.electricity), .meter-section .meter-icon.fa-bolt');
        if (electricitySection) {
            electricitySection.setAttribute('data-component', 'electricity-meter-section');
            electricitySection.setAttribute('data-component-id', 'electricity-meter-section-1');
        }

        // Tier breakdown
        document.querySelectorAll('.breakdown-details .detail-row').forEach((row, index) => {
            const label = row.querySelector('.billing-label')?.textContent || '';
            if (label.includes('Tier') || label.match(/Tier \d+/)) {
                row.setAttribute('data-component', 'tier-breakdown');
                row.setAttribute('data-component-id', `tier-breakdown-${index + 1}`);
            } else if (label.toLowerCase().includes('sewerage') || label.toLowerCase().includes('water out')) {
                row.setAttribute('data-component', 'water-out-charge');
                row.setAttribute('data-component-id', `water-out-charge-${index + 1}`);
            } else if (label.toLowerCase().includes('additional') || label.toLowerCase().includes('surcharge')) {
                row.setAttribute('data-component', 'additional-charges');
                row.setAttribute('data-component-id', `additional-charges-${index + 1}`);
            } else if (label.toLowerCase().includes('fixed')) {
                row.setAttribute('data-component', 'fixed-costs');
                row.setAttribute('data-component-id', `fixed-costs-${index + 1}`);
            } else if (label.toLowerCase().includes('customer')) {
                row.setAttribute('data-component', 'customer-costs');
                row.setAttribute('data-component-id', `customer-costs-${index + 1}`);
            }
        });

        // Totals
        document.querySelectorAll('.billing-row').forEach((row, index) => {
            const label = row.querySelector('.billing-label')?.textContent || '';
            if (label.includes('Consumption Total')) {
                row.setAttribute('data-component', 'consumption-total');
                row.setAttribute('data-component-id', `consumption-total-1`);
            } else if (label.includes('VAT')) {
                row.setAttribute('data-component', 'vat-amount');
                row.setAttribute('data-component-id', `vat-amount-1`);
            } else if (label.includes('Period Total')) {
                row.setAttribute('data-component', 'period-total');
                row.setAttribute('data-component-id', `period-total-1`);
            }
        });

        const grandTotal = document.querySelector('.total-amount');
        if (grandTotal) {
            grandTotal.setAttribute('data-component', 'grand-total');
            grandTotal.setAttribute('data-component-id', 'grand-total-1');
        }

        // Readings page components
        const readingsSection = document.querySelector('.readings-container');
        if (readingsSection) {
            readingsSection.setAttribute('data-component', 'readings-section');
            readingsSection.setAttribute('data-component-id', 'readings-section-1');
        }

        const waterInput = document.querySelector('#waterInputSection, .digit-input-section:has(#waterKilolitersGroup)');
        if (waterInput) {
            waterInput.setAttribute('data-component', 'water-reading-input');
            waterInput.setAttribute('data-component-id', 'water-reading-input-1');
        }

        const electricityInput = document.querySelector('#electricityInputSection, .digit-input-section:has(#electricityGroup)');
        if (electricityInput) {
            electricityInput.setAttribute('data-component', 'electricity-reading-input');
            electricityInput.setAttribute('data-component-id', 'electricity-reading-input-1');
        }

        const readingHistory = document.querySelector('.reading-history');
        if (readingHistory) {
            readingHistory.setAttribute('data-component', 'reading-history');
            readingHistory.setAttribute('data-component-id', 'reading-history-1');
        }

        // Accounts page components
        const paymentEntry = document.querySelector('.payment-section');
        if (paymentEntry) {
            paymentEntry.setAttribute('data-component', 'payment-entry');
            paymentEntry.setAttribute('data-component-id', 'payment-entry-1');
        }

        const periodsContainer = document.querySelector('.periods-container');
        if (periodsContainer) {
            periodsContainer.setAttribute('data-component', 'billing-periods');
            periodsContainer.setAttribute('data-component-id', 'billing-periods-1');
        }

        document.querySelectorAll('.period-card').forEach((card, index) => {
            card.setAttribute('data-component', 'period-card');
            card.setAttribute('data-component-id', `period-card-${index + 1}`);
        });

        // Navigation
        const bottomNav = document.querySelector('.fixed-nav-tabs, .bottom-navigation');
        if (bottomNav) {
            bottomNav.setAttribute('data-component', 'bottom-navigation');
            bottomNav.setAttribute('data-component-id', 'bottom-navigation-1');
        }
    }

    // Clean label text - remove HTML, formatting, and common prefixes
    function cleanLabelText(text) {
        if (!text) return '';
        
        // Create temp div to strip HTML tags
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = text;
        let cleanText = tempDiv.textContent || tempDiv.innerText || '';
        
        // Remove common prefixes
        cleanText = cleanText.replace(/^(Admin|Enter|Select|Click to|Please|Template|Applicable)\s+/i, '').trim();
        
        // Remove trailing colons and whitespace
        cleanText = cleanText.replace(/^[:\s*]+|[:\s*]+$/g, '').trim();
        
        // Remove "strong" markers
        cleanText = cleanText.replace(/\*\*/g, '').trim();
        
        // Remove parenthetical text like "(Auto)", "(As per bill)", etc. but keep main text
        cleanText = cleanText.replace(/\s*\([^)]*\)\s*/g, '').trim();
        
        return cleanText;
    }

    // Get field description from label or data attribute
    function getFieldDescription(element) {
        if (!element) return null;
        
        // First, check for data-component-description attribute
        let description = element.getAttribute('data-component-description');
        if (description) {
            return description.trim();
        }

        // Try to find associated label by ID (most reliable)
        const elementId = element.id;
        if (elementId) {
            const label = document.querySelector(`label[for="${elementId}"]`);
            if (label) {
                const labelText = cleanLabelText(label.textContent || label.innerText || '');
                if (labelText) {
                    return labelText;
                }
            }
        }

        // Try to find label in parent form-group (most common case)
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            // Check if parent is form-group
            if (parent.classList && parent.classList.contains('form-group')) {
                const label = parent.querySelector('label');
                if (label) {
                    const labelText = cleanLabelText(label.textContent || label.innerText || '');
                    if (labelText) {
                        return labelText;
                    }
                }
            }
            
            // Check if parent is row/col structure
            if (parent.classList && (parent.classList.contains('row') || parent.classList.contains('form-row') || parent.classList.contains('col'))) {
                const label = parent.querySelector('label');
                if (label) {
                    const labelText = cleanLabelText(label.textContent || label.innerText || '');
                    if (labelText) {
                        return labelText;
                    }
                }
            }
            
            // Check if parent has a label as direct child
            if (parent.querySelector && parent.querySelector('label')) {
                const label = parent.querySelector('label');
                // Make sure label is before or near the input
                const labelText = cleanLabelText(label.textContent || label.innerText || '');
                if (labelText) {
                    return labelText;
                }
            }
            
            parent = parent.parentElement;
        }

        // Try placeholder as fallback
        const placeholder = element.getAttribute('placeholder');
        if (placeholder && placeholder.length > 0) {
            let cleanPlaceholder = placeholder.trim();
            // Remove common prefixes
            cleanPlaceholder = cleanPlaceholder.replace(/^(Enter|Select|Click to|Please|Template|Applicable)\s+/i, '').trim();
            // If placeholder matches common patterns, use it
            if (cleanPlaceholder && cleanPlaceholder.length > 0) {
                return cleanPlaceholder;
            }
        }

        // Try name attribute as last resort
        const nameAttr = element.getAttribute('name');
        if (nameAttr) {
            // Convert snake_case or kebab-case to Title Case
            const nameParts = nameAttr.replace(/[_-]/g, ' ').split(' ').filter(p => p.length > 0);
            if (nameParts.length > 0) {
                const titleCase = nameParts.map(part => {
                    // Handle common abbreviations
                    if (part.toUpperCase() === 'ID' || part.toUpperCase() === 'KL' || part.toUpperCase() === 'KWH' || part.toUpperCase() === 'VAT') {
                        return part.toUpperCase();
                    }
                    return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
                }).join(' ');
                return titleCase;
            }
        }

        return null;
    }

    // Handle right-click to show component debug info
    function handleRightClick(event) {
        // First, check if we clicked directly on an input/select/textarea/button/label
        let clickedElement = event.target;
        let element = clickedElement;
        let componentId = null;
        let componentType = null;
        let componentDescription = null;

        // Check if clicked element is a form field
        const tagName = clickedElement.tagName ? clickedElement.tagName.toLowerCase() : '';
        const isFormField = tagName === 'input' || tagName === 'select' || tagName === 'textarea' || tagName === 'button';
        const isLabel = tagName === 'label';

        // If clicked on a label, find the associated input/select/textarea
        if (isLabel) {
            const forAttr = clickedElement.getAttribute('for');
            if (forAttr) {
                const associatedField = document.getElementById(forAttr);
                if (associatedField) {
                    clickedElement = associatedField;
                    element = associatedField;
                }
            }
        }

        // PRIORITY 1: If clicked directly on a form field, ALWAYS use it as the primary element
        // This ensures we show the field name, not the container
        if (isFormField || (isLabel && element !== clickedElement)) {
            // Get description from label or placeholder FIRST (before checking data-component)
            componentDescription = getFieldDescription(element);
            
            // Check if this element has data-component attribute
            componentType = element.getAttribute('data-component');
            componentId = element.getAttribute('data-component-id');
            
            // If no data-component, determine type from element
            if (!componentType) {
                const elemTag = element.tagName.toLowerCase();
                if (elemTag === 'input') {
                    const inputType = element.type || 'text';
                    componentType = inputType === 'number' ? 'number-input' : 
                                   inputType === 'email' ? 'email-input' : 
                                   inputType === 'password' ? 'password-input' : 
                                   inputType === 'date' ? 'date-input' : 
                                   inputType === 'checkbox' ? 'checkbox-input' :
                                   inputType === 'radio' ? 'radio-input' :
                                   'text-input';
                } else if (elemTag === 'select') {
                    componentType = 'select-input';
                } else if (elemTag === 'textarea') {
                    componentType = 'textarea-input';
                } else if (elemTag === 'button') {
                    componentType = 'button';
                }
                
                // Generate component ID
                if (element.id) {
                    componentId = element.id + '-component';
                } else if (element.name) {
                    componentId = element.name + '-component';
                } else {
                    componentId = componentType + '-' + Math.random().toString(36).substr(2, 9);
                }
            }
            
            // If we have a description but no data-component-description attribute, use it
            if (componentDescription && !element.getAttribute('data-component-description')) {
                // Description already set above
            }
        } else {
            // PRIORITY 2: Walk up the DOM tree to find element with data-component
            let foundContainer = false;
            while (element && element !== document.body) {
                componentType = element.getAttribute('data-component');
                componentId = element.getAttribute('data-component-id');
                const desc = element.getAttribute('data-component-description');
                
                if (desc) {
                    componentDescription = desc;
                }
                
                if (componentType) {
                    foundContainer = true;
                    break;
                }
                
                element = element.parentElement;
            }
            
            // If we found a container but clicked on a form field inside, prioritize the field description
            if (foundContainer && isFormField) {
                const fieldDesc = getFieldDescription(clickedElement);
                if (fieldDesc) {
                    componentDescription = fieldDesc;
                    // Also update element to the clicked field for better context
                    element = clickedElement;
                }
            } else if (!foundContainer && isFormField) {
                // No container found, but we have a form field - use it
                element = clickedElement;
                componentDescription = getFieldDescription(element);
                
                // Determine type
                const elemTag = element.tagName.toLowerCase();
                if (elemTag === 'input') {
                    const inputType = element.type || 'text';
                    componentType = inputType === 'number' ? 'number-input' : 
                                   inputType === 'email' ? 'email-input' : 
                                   inputType === 'password' ? 'password-input' : 
                                   inputType === 'date' ? 'date-input' : 
                                   inputType === 'checkbox' ? 'checkbox-input' :
                                   inputType === 'radio' ? 'radio-input' :
                                   'text-input';
                } else if (elemTag === 'select') {
                    componentType = 'select-input';
                } else if (elemTag === 'textarea') {
                    componentType = 'textarea-input';
                } else if (elemTag === 'button') {
                    componentType = 'button';
                }
                
                // Generate component ID
                if (element.id) {
                    componentId = element.id + '-component';
                } else if (element.name) {
                    componentId = element.name + '-component';
                } else {
                    componentId = componentType + '-' + Math.random().toString(36).substr(2, 9);
                }
            }
        }

        // If no component found, don't show debug
        if (!componentType) {
            return; // Allow default context menu
        }

        // Prevent default context menu
        event.preventDefault();
        event.stopPropagation();

        // Debug: Log component found
        console.log('[Component Debug] Found component:', componentType, 'ID:', componentId, 'Description:', componentDescription);

        // Get field description if not already set
        if (!componentDescription) {
            componentDescription = getFieldDescription(element);
        }

        // Get component info from registry
        let componentInfo = componentRegistry[componentType] || {
            name: componentDescription || componentType,
            description: componentDescription || 'Component information not available',
            purpose: componentDescription ? `Field for entering/displaying: ${componentDescription}` : 'Unknown',
            dataSource: 'Unknown',
            outputs: 'Unknown'
        };

        // ALWAYS override name and description if we have a field description
        // This ensures the actual field name (e.g., "Billing Day") is shown, not the generic type
        if (componentDescription) {
            componentInfo = {
                ...componentInfo,
                name: componentDescription,
                description: componentDescription,
                purpose: `Field for entering/displaying: ${componentDescription}`
            };
        } else if (!componentInfo.name || componentInfo.name === componentType) {
            // If no description found, try one more time with the element
            const finalDesc = getFieldDescription(element);
            if (finalDesc) {
                componentInfo = {
                    ...componentInfo,
                    name: finalDesc,
                    description: finalDesc,
                    purpose: `Field for entering/displaying: ${finalDesc}`
                };
            }
        }

        // Get current page info
        const currentPage = window.location.pathname;
        const pageName = getPageName(currentPage);

        // Get element details
        const elementTag = element.tagName.toLowerCase();
        const elementClasses = element.className || '';
        const elementId = element.id || '';

        // Create debug information
        const debugInfo = {
            componentName: componentInfo.name,
            componentId: componentId,
            componentType: componentType,
            description: componentInfo.description,
            purpose: componentInfo.purpose,
            dataSource: componentInfo.dataSource,
            outputs: componentInfo.outputs,
            currentPage: currentPage,
            pageName: pageName,
            elementTag: elementTag,
            elementClasses: elementClasses,
            elementId: elementId
        };

        // Show debug dialog
        showDebugDialog(debugInfo);
    }

    // Get page name from URL
    function getPageName(path) {
        if (path.includes('/dashboard')) return 'Dashboard';
        if (path.includes('/readings')) return 'Readings';
        if (path.includes('/accounts')) return 'Accounts';
        if (path.includes('/admin/user-accounts')) return 'User Accounts';
        if (path.includes('/admin/settings')) return 'Settings';
        if (path.includes('/admin/create-user') || path.includes('/admin/edit-user')) return 'User Management';
        if (path.includes('/admin/create-account') || path.includes('/admin/edit-account')) return 'Account Management';
        if (path.includes('/admin/create-site') || path.includes('/admin/edit-site')) return 'Site Management';
        if (path.includes('/admin/create-meter') || path.includes('/admin/edit-meter')) return 'Meter Management';
        if (path.includes('/admin/create-meter-reading') || path.includes('/admin/edit-meter-reading')) return 'Meter Reading';
        if (path.includes('/admin/create-payment')) return 'Payment Entry';
        if (path.includes('/admin/create-region') || path.includes('/admin/edit-region')) return 'Region Management';
        if (path.includes('/admin/tariff-template')) return 'Tariff Template';
        if (path.includes('/admin/login')) return 'Login';
        if (path.includes('/admin/forgot-password')) return 'Forgot Password';
        if (path.includes('/admin')) return 'Admin Panel';
        return 'Unknown Page';
    }

    // Show debug dialog
    function showDebugDialog(debugInfo) {
        // Create formatted text for clipboard
        const copyText = `Component Debug Information:
Component Name: ${debugInfo.componentName}
Component ID: ${debugInfo.componentId}
Component Type: ${debugInfo.componentType}
Description: ${debugInfo.description}
Purpose: ${debugInfo.purpose}
Data Source: ${debugInfo.dataSource}
Outputs: ${debugInfo.outputs}
Current Page: ${debugInfo.currentPage}
Page Name: ${debugInfo.pageName}
Element: <${debugInfo.elementTag}${debugInfo.elementId ? ' id="' + debugInfo.elementId + '"' : ''}${debugInfo.elementClasses ? ' class="' + debugInfo.elementClasses + '"' : ''} />`;

        // Create dialog HTML
        const dialogHTML = `
            <div style="text-align: left; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px;">
                <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #3294B8;">
                    <h3 style="margin: 0; color: #3294B8; font-size: 18px;">${debugInfo.componentName}</h3>
                    <p style="margin: 4px 0 0 0; color: #666; font-size: 12px;">ID: ${debugInfo.componentId}</p>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <strong style="color: #333;">Description:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 14px; line-height: 1.5;">${debugInfo.description}</p>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <strong style="color: #333;">Purpose:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 14px;">${debugInfo.purpose}</p>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <strong style="color: #333;">Data Source:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 14px; font-family: 'Courier New', monospace; background: #f5f5f5; padding: 8px; border-radius: 4px;">${debugInfo.dataSource}</p>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <strong style="color: #333;">Outputs/Adds Value To:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 14px; font-family: 'Courier New', monospace; background: #f5f5f5; padding: 8px; border-radius: 4px;">${debugInfo.outputs}</p>
                </div>
                
                <div style="margin-bottom: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                    <strong style="color: #333;">Page Information:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 13px;">Page: ${debugInfo.pageName}</p>
                    <p style="margin: 4px 0; color: #555; font-size: 13px;">Route: ${debugInfo.currentPage}</p>
                </div>
                
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                    <strong style="color: #333;">Element Details:</strong>
                    <p style="margin: 4px 0; color: #555; font-size: 13px; font-family: 'Courier New', monospace;">&lt;${debugInfo.elementTag}${debugInfo.elementId ? ' id="' + debugInfo.elementId + '"' : ''}${debugInfo.elementClasses ? ' class="' + debugInfo.elementClasses.split(' ').slice(0, 3).join(' ') + '..."' : ''} /&gt;</p>
                </div>
            </div>
        `;

        // Create modal dialog
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;

        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        `;

        dialog.innerHTML = dialogHTML;

        // Add buttons
        const buttonContainer = document.createElement('div');
        buttonContainer.style.cssText = 'display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e0e0;';

        const copyButton = document.createElement('button');
        copyButton.textContent = 'Copy to Clipboard';
        copyButton.style.cssText = `
            background: #3294B8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        `;
        copyButton.onmouseover = () => copyButton.style.background = '#2878a0';
        copyButton.onmouseout = () => copyButton.style.background = '#3294B8';

        const closeButton = document.createElement('button');
        closeButton.textContent = 'Close';
        closeButton.style.cssText = `
            background: #f5f5f5;
            color: #666;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        `;
        closeButton.onmouseover = () => closeButton.style.background = '#e0e0e0';
        closeButton.onmouseout = () => closeButton.style.background = '#f5f5f5';

        const closeModal = () => {
            document.body.removeChild(modal);
        };

        const copyToClipboard = () => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copyText).then(() => {
                    copyButton.textContent = 'Copied!';
                    copyButton.style.background = '#4CAF50';
                    setTimeout(() => {
                        copyButton.textContent = 'Copy to Clipboard';
                        copyButton.style.background = '#3294B8';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    alert('Failed to copy to clipboard');
                });
            } else {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = copyText;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    copyButton.textContent = 'Copied!';
                    copyButton.style.background = '#4CAF50';
                    setTimeout(() => {
                        copyButton.textContent = 'Copy to Clipboard';
                        copyButton.style.background = '#3294B8';
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy to clipboard');
                }
                document.body.removeChild(textArea);
            }
        };

        copyButton.onclick = copyToClipboard;
        closeButton.onclick = closeModal;
        modal.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        buttonContainer.appendChild(copyButton);
        buttonContainer.appendChild(closeButton);
        dialog.appendChild(buttonContainer);
        modal.appendChild(dialog);
        document.body.appendChild(modal);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComponentDebug);
    } else {
        initComponentDebug();
    }

    // Debug: Log initialization
    console.log('[Component Debug] Script loaded and initialized');
})();





















