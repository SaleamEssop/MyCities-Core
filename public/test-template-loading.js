/**
 * Template Loading Diagnostic Test
 * 
 * Run this in the browser console on the billing-calculator-php page
 * to diagnose template loading issues.
 * 
 * Usage:
 * 1. Open http://localhost/admin/billing-calculator-php
 * 2. Open browser console (F12)
 * 3. Copy and paste this entire script
 * 4. Press Enter
 */

(async function() {
    console.log('=== TEMPLATE LOADING DIAGNOSTIC TEST ===\n');
    
    const results = {
        passed: [],
        failed: [],
        warnings: []
    };
    
    function test(name, condition, message) {
        if (condition) {
            console.log(`✅ PASS: ${name}`);
            results.passed.push(name);
            if (message) console.log(`   ${message}`);
        } else {
            console.error(`❌ FAIL: ${name}`);
            results.failed.push(name);
            if (message) console.error(`   ${message}`);
        }
    }
    
    function warn(name, message) {
        console.warn(`⚠️  WARN: ${name}`);
        results.warnings.push(name);
        if (message) console.warn(`   ${message}`);
    }
    
    // Test 1: Check if page loaded
    test('Page loaded', typeof window !== 'undefined', 'Window object exists');
    
    // Test 2: Check if apiBaseUrl is defined
    test('apiBaseUrl defined', typeof apiBaseUrl !== 'undefined', 
        `apiBaseUrl = ${typeof apiBaseUrl !== 'undefined' ? apiBaseUrl : 'undefined'}`);
    
    // Test 3: Check if LaravelAPI is defined
    test('LaravelAPI defined', typeof LaravelAPI !== 'undefined', 
        `LaravelAPI type = ${typeof LaravelAPI}`);
    
    // Test 4: Check if getTariffTemplates exists
    test('getTariffTemplates exists', 
        typeof LaravelAPI !== 'undefined' && typeof LaravelAPI.getTariffTemplates === 'function',
        'LaravelAPI.getTariffTemplates is a function');
    
    // Test 5: Check if loadTariffTemplates exists
    test('loadTariffTemplates exists', 
        typeof window.loadTariffTemplates === 'function',
        'window.loadTariffTemplates is a function');
    
    // Test 6: Check if allTariffTemplates is defined
    test('allTariffTemplates defined', typeof allTariffTemplates !== 'undefined',
        `allTariffTemplates type = ${typeof allTariffTemplates}, length = ${allTariffTemplates?.length || 0}`);
    
    // Test 7: Check if updateTemplateDropdowns exists
    test('updateTemplateDropdowns exists', typeof updateTemplateDropdowns === 'function',
        'updateTemplateDropdowns is a function');
    
    // Test 8: Check if populateTemplateDropdown exists
    test('populateTemplateDropdown exists', typeof populateTemplateDropdown === 'function',
        'populateTemplateDropdown is a function');
    
    // Test 9: Check if filterTemplatesByBillingType exists
    test('filterTemplatesByBillingType exists', typeof filterTemplatesByBillingType === 'function',
        'filterTemplatesByBillingType is a function');
    
    // Test 10: Check if dropdowns exist
    const periodDropdown = document.getElementById('tariff_template_select');
    const sectorDropdown = document.getElementById('sector_tariff_template_select');
    test('Period dropdown exists', periodDropdown !== null, 
        `Found: ${periodDropdown ? 'YES' : 'NO'}`);
    test('Sector dropdown exists', sectorDropdown !== null,
        `Found: ${sectorDropdown ? 'YES' : 'NO'}`);
    
    // Test 11: Try to call the API
    console.log('\n--- Testing API Call ---');
    try {
        if (typeof LaravelAPI !== 'undefined' && typeof LaravelAPI.getTariffTemplates === 'function') {
            console.log('Calling LaravelAPI.getTariffTemplates()...');
            const templates = await LaravelAPI.getTariffTemplates();
            test('API call succeeded', Array.isArray(templates),
                `Returned: ${Array.isArray(templates) ? 'Array' : typeof templates}, length: ${templates?.length || 0}`);
            
            if (Array.isArray(templates) && templates.length > 0) {
                console.log(`\nFirst template:`, templates[0]);
                test('Templates have required fields', 
                    templates[0].id && templates[0].name,
                    `Has id: ${!!templates[0].id}, Has name: ${!!templates[0].name}`);
            } else {
                warn('No templates returned', 'API returned empty array or no templates in database');
            }
        } else {
            test('API call possible', false, 'LaravelAPI.getTariffTemplates not available');
        }
    } catch (error) {
        test('API call succeeded', false, `Error: ${error.message}`);
        console.error('Full error:', error);
    }
    
    // Test 12: Check current template state
    console.log('\n--- Current State ---');
    console.log('allTariffTemplates:', allTariffTemplates);
    console.log('allTariffTemplates length:', allTariffTemplates?.length || 0);
    
    if (periodDropdown) {
        console.log('Period dropdown options:', periodDropdown.options.length);
        for (let i = 0; i < periodDropdown.options.length; i++) {
            console.log(`  Option ${i}: ${periodDropdown.options[i].text} (value: ${periodDropdown.options[i].value})`);
        }
    }
    
    if (sectorDropdown) {
        console.log('Sector dropdown options:', sectorDropdown.options.length);
        for (let i = 0; i < sectorDropdown.options.length; i++) {
            console.log(`  Option ${i}: ${sectorDropdown.options[i].text} (value: ${sectorDropdown.options[i].value})`);
        }
    }
    
    // Test 13: Try to manually load templates
    console.log('\n--- Manual Template Load Test ---');
    try {
        if (typeof window.loadTariffTemplates === 'function') {
            console.log('Calling window.loadTariffTemplates()...');
            const result = await window.loadTariffTemplates();
            test('Manual load succeeded', Array.isArray(result),
                `Returned: ${Array.isArray(result) ? 'Array' : typeof result}, length: ${result?.length || 0}`);
            
            // Check dropdowns after load
            setTimeout(() => {
                console.log('\n--- After Load Check ---');
                if (periodDropdown) {
                    const periodOptions = periodDropdown.options.length;
                    test('Period dropdown populated', periodOptions > 1,
                        `Options: ${periodOptions} (should be > 1)`);
                }
                if (sectorDropdown) {
                    const sectorOptions = sectorDropdown.options.length;
                    test('Sector dropdown populated', sectorOptions > 1,
                        `Options: ${sectorOptions} (should be > 1)`);
                }
                
                // Final summary
                console.log('\n=== TEST SUMMARY ===');
                console.log(`✅ Passed: ${results.passed.length}`);
                console.log(`❌ Failed: ${results.failed.length}`);
                console.log(`⚠️  Warnings: ${results.warnings.length}`);
                
                if (results.failed.length === 0) {
                    console.log('\n✅ ALL TESTS PASSED!');
                } else {
                    console.log('\n❌ SOME TESTS FAILED:');
                    results.failed.forEach(f => console.log(`   - ${f}`));
                }
            }, 1000);
        } else {
            test('Manual load possible', false, 'window.loadTariffTemplates not available');
        }
    } catch (error) {
        test('Manual load succeeded', false, `Error: ${error.message}`);
        console.error('Full error:', error);
    }
    
    return results;
})();









