/* ==================== MODULE 1: PERIOD CALCULATION (PROTECTED) ==================== */

// @PROTECTED_MODULE: BillingEngineLogic
// This container encapsulates ALL billing calculation logic, sector system, validation, and data operations
// CRITICAL: All logic functions, state, and calculations are contained here
// This module can be extracted and reused in Laravel Blade + Vue applications

const BillingEngineLogic = (function() {
  'use strict';
  
  // ==================== PRIVATE STATE ====================
  let periods = [];
  let active = null;
  let debug_current_period_index = null;  // For step-by-step debugging
  
  // ==================== PRIVATE HELPER FUNCTIONS ====================
  
  function iso(d){ 
    return d.toISOString().slice(0,10); 
  }

  function format_date(d){
    const day = d.getDate();
    const suffix =
      day % 10 === 1 && day !== 11 ? "st" :
      day % 10 === 2 && day !== 12 ? "nd" :
      day % 10 === 3 && day !== 13 ? "rd" : "th";
    return `${day}${suffix} ${d.toLocaleString("en-GB",{month:"short"})} ${d.getFullYear()}`;
  }

  function format_date_range(start_date, end_date){
    const start = new Date(start_date);
    const end = new Date(end_date);
    const start_day = start.getDate();
    const end_day = end.getDate();
    const start_month = start.toLocaleString("en-GB",{month:"short"});
    const end_month = end.toLocaleString("en-GB",{month:"short"});
    
    if(start_month === end_month){
      return `${start_day}–${end_day} ${start_month}`;
    } else {
      return `${start_day} ${start_month}–${end_day} ${end_month}`;
    }
  }

  function format_number(num){
    return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
  }

  function format_period_display(period) {
    const start = new Date(period.start);
    const end = new Date(period.end);
    end.setDate(end.getDate() - 1); // Period end is exclusive, so display end - 1 day
    return `${format_date(start)} -> ${format_date(end)}`;
  }

  function days_between(a, b){
    // Both dates are inclusive, so we add 1 to the difference
    // Example: Jan 1 to Jan 10 = 10 days (inclusive)
    const dateA = new Date(a);
    const dateB = new Date(b);
    dateA.setHours(12, 0, 0, 0);
    dateB.setHours(12, 0, 0, 0);
    const diffTime = Math.abs(dateB - dateA);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays + 1; // +1 because both dates are inclusive
  }

  function get_tiers(){
    // Must use template tiers - no fallback to table
    if (typeof currentTemplateTiers !== 'undefined' && currentTemplateTiers !== null && currentTemplateTiers.length > 0) {
      return currentTemplateTiers;
    }
    
    // No template selected - return empty and show error
    log_error("No tariff template selected. Please select a tariff template first.");
    return [];
  }

  function get_all_readings_sorted(){
    const all_readings = [];
    periods.forEach((p, period_idx) => {
      p.readings
        .filter(r => r.date && r.value !== null)
        .forEach(r => {
          all_readings.push({
            date: new Date(r.date),
            value: r.value,
            period_index: period_idx
          });
        });
    });
    all_readings.sort((a, b) => a.date - b.date);
    return all_readings;
  }

  function calculate_tier_cost_for_litres(litres){
    if(litres === null || litres === undefined || litres <= 0){
      return { total_cost: 0, breakdown: [] };
    }
    
    const tiers = get_tiers();
    if(tiers.length === 0){
      return { total_cost: 0, breakdown: [] };
    }
    
    let remaining = litres;
    let prev = 0;
    let total_cost = 0;
    const breakdown = [];
    
    for(const t of tiers){
      const cap = t.max - prev;
      const used = Math.max(0, Math.min(remaining, cap));
      
      if(used > 0){
        const cost = (used / 1000) * t.rate;
        breakdown.push({
          prev: prev,
          max: t.max,
          used: used,
          rate: t.rate,
          cost: cost
        });
        total_cost += cost;
        remaining -= used;
      }
      
      prev = t.max;
      if(remaining <= 0) break;
    }
    
    if(remaining > 0 && tiers.length > 0){
      const last_tier = tiers[tiers.length - 1];
      const cost = (remaining / 1000) * last_tier.rate;
      breakdown.push({
        prev: prev,
        max: Infinity,
        used: remaining,
        rate: last_tier.rate,
        cost: cost
      });
      total_cost += cost;
    }
    
    return { total_cost: total_cost, breakdown: breakdown };
  }

  function calculate_reconciliation_tier_cost(adjustment_litres, provisioned_usage, calculated_usage){
    if(adjustment_litres === null || adjustment_litres === undefined || adjustment_litres === 0){
      return null;
    }
    
    if(provisioned_usage === null || provisioned_usage === undefined || 
       calculated_usage === null || calculated_usage === undefined){
      return null;
    }
    
    const provisioned_cost = calculate_tier_cost_for_litres(provisioned_usage);
    const calculated_cost = calculate_tier_cost_for_litres(calculated_usage);
    const reconciliation_cost = calculated_cost.total_cost - provisioned_cost.total_cost;
    
    return {
      total_cost: reconciliation_cost,
      provisioned_cost: provisioned_cost.total_cost,
      calculated_cost: calculated_cost.total_cost,
      provisioned_breakdown: provisioned_cost.breakdown,
      calculated_breakdown: calculated_cost.breakdown,
      adjustment_litres: adjustment_litres,
      provisioned_litres: provisioned_usage,
      calculated_litres: calculated_usage
    };
  }

  function create_sectors_from_readings(){
    const all_readings = get_all_readings_sorted();
    if(all_readings.length < 2) return [];
    
    const sectors = [];
    let sector_id = 1;
    
    for(let i = 0; i < all_readings.length - 1; i++){
      const earlier = all_readings[i];
      const later = all_readings[i + 1];
      
      let sector_start_date;
      let sector_start_reading;
      
      if(i === 0){
        sector_start_date = new Date(earlier.date);
        sector_start_reading = earlier.value;
      } else {
        sector_start_date = new Date(earlier.date);
        sector_start_date.setDate(sector_start_date.getDate() + 1);
        sector_start_reading = earlier.value;
      }
      
      const sector_end_date = new Date(later.date);
      const sector_days = days_between(sector_start_date, sector_end_date);
      const sector_usage = later.value - sector_start_reading;
      
      // DEFENSIVE CHECK: Prevent negative sector usage
      if(sector_usage < 0) {
        throw new Error(`INVALID: Sector usage cannot be negative. Reading ${later.value} L on ${iso(later.date)} is lower than start reading ${sector_start_reading} L. Please correct the reading values.`);
      }
      
      const sector = {
        sector_id: sector_id++,
        start_date: sector_start_date,
        end_date: sector_end_date,
        start_reading: sector_start_reading,
        end_reading: later.value,
        sector_usage: sector_usage,
        sector_days: sector_days,
        daily_usage: 0,
        sub_sectors: [],
        crosses_period: false
      };
      
      if(sector_days > 0){
        sector.daily_usage = sector_usage / sector_days;
      }
      
      split_sector_at_period_boundaries(sector);
      sectors.push(sector);
    }
    
    return sectors;
  }

  function split_sector_at_period_boundaries(sector){
    const sector_start_normalized = new Date(sector.start_date);
    sector_start_normalized.setHours(12, 0, 0, 0);
    const sector_end_normalized = new Date(sector.end_date);
    sector_end_normalized.setHours(12, 0, 0, 0);
    
    const boundaries = [];
    periods.forEach((p, idx) => {
      const period_end_normalized = new Date(p.end);
      period_end_normalized.setHours(12, 0, 0, 0);
      
      if(sector_start_normalized.getTime() < period_end_normalized.getTime() && 
         sector_end_normalized.getTime() >= period_end_normalized.getTime()){
        boundaries.push({period_index: idx, end_date: p.end});
      }
    });
    
    if(boundaries.length === 0){
      sector.crosses_period = false;
      return;
    }
    
    boundaries.sort((a, b) => a.end_date - b.end_date);
    
    sector.crosses_period = true;
    let current_start = new Date(sector.start_date);
    let sub_id_letter = 'a';
    
    boundaries.forEach((boundary, idx) => {
      const period_end = new Date(boundary.end_date);
      period_end.setHours(12, 0, 0, 0);
      const current_start_normalized = new Date(current_start);
      current_start_normalized.setHours(12, 0, 0, 0);
      
      const period_end_inclusive = new Date(period_end);
      period_end_inclusive.setDate(period_end_inclusive.getDate() - 1);
      const days_before = days_between(current_start_normalized, period_end_inclusive);
      const usage_before = sector.daily_usage * days_before;
      
      const sub_id = `${sector.sector_id}${sub_id_letter}`;
      sub_id_letter = String.fromCharCode(sub_id_letter.charCodeAt(0) + 1);
      
      sector.sub_sectors.push({
        sub_id: sub_id,
        period_index: boundary.period_index,
        start_date: new Date(current_start),
        end_date: new Date(period_end),
        days_in_period: days_before,
        usage_in_period: usage_before
      });
      
      current_start = period_end;
    });
    
    if(current_start < sector.end_date){
      let end_period_idx = -1;
      for(let i = 0; i < periods.length; i++){
        if(sector.end_date >= periods[i].start && sector.end_date < periods[i].end){
          end_period_idx = i;
          break;
        }
      }
      
      if(end_period_idx >= 0){
        const current_start_normalized = new Date(current_start);
        current_start_normalized.setHours(12, 0, 0, 0);
        const sector_end_normalized = new Date(sector.end_date);
        sector_end_normalized.setHours(12, 0, 0, 0);
        
        const days_after = days_between(current_start_normalized, sector_end_normalized);
        const usage_after = sector.daily_usage * days_after;
        
        const sub_id = `${sector.sector_id}${sub_id_letter}`;
        
        sector.sub_sectors.push({
          sub_id: sub_id,
          period_index: end_period_idx,
          start_date: new Date(current_start),
          end_date: new Date(sector.end_date),
          days_in_period: days_after,
          usage_in_period: usage_after
        });
      }
    }
  }

  function get_sectors_for_period(period_index){
    if(period_index === null || period_index < 0 || period_index >= periods.length) return [];
    
    const all_sectors = create_sectors_from_readings();
    const period_sectors = [];
    const p = periods[period_index];
    
    const period_start = new Date(p.start);
    period_start.setHours(12, 0, 0, 0);
    const period_end = new Date(p.end);
    period_end.setHours(12, 0, 0, 0);
    
    all_sectors.forEach(sector => {
      const sector_start = new Date(sector.start_date);
      sector_start.setHours(12, 0, 0, 0);
      const sector_end = new Date(sector.end_date);
      sector_end.setHours(12, 0, 0, 0);
      
      if(!sector.crosses_period){
        if(sector_start.getTime() >= period_start.getTime() && 
           sector_end.getTime() < period_end.getTime()){
          period_sectors.push({
            sector_id: sector.sector_id,
            sub_id: null,
            start_date: new Date(sector.start_date),
            end_date: new Date(sector.end_date),
            start_reading: sector.start_reading,
            end_reading: sector.end_reading,
            total_usage: sector.sector_usage,
            days_in_period: sector.sector_days,
            usage_in_period: sector.sector_usage,
            daily_usage: sector.daily_usage
          });
        }
      } else {
        sector.sub_sectors.forEach(sub => {
          if(sub.period_index === period_index){
            const all_readings = get_all_readings_sorted();
            const reading_before_sector = all_readings.find(r => r.value === sector.start_reading);
            
            if(reading_before_sector){
              const sub_start_normalized = new Date(sub.start_date);
              sub_start_normalized.setHours(12, 0, 0, 0);
              
              let start_reading;
              let end_reading;
              
              const period_start_match = periods.find((period, idx) => {
                const period_start_check = new Date(period.start);
                period_start_check.setHours(12, 0, 0, 0);
                return period_start_check.getTime() === sub_start_normalized.getTime() && idx > 0;
              });
              
              if(period_start_match){
                const period_idx = periods.indexOf(period_start_match);
                const prev_period = periods[period_idx - 1];
                // Use closing reading from previous period
                start_reading = prev_period.closing || 0;
                end_reading = start_reading + sub.usage_in_period;
              } else {
                const reading_date = new Date(reading_before_sector.date);
                reading_date.setHours(12, 0, 0, 0);
                
                let days_from_reading;
                if(sub_start_normalized.getTime() === reading_date.getTime()){
                  days_from_reading = 0;
                } else if(sub_start_normalized.getTime() > reading_date.getTime()){
                  const days_calc = days_between(reading_date, sub_start_normalized);
                  days_from_reading = (days_calc === 2) ? 0 : (days_calc - 1);
                } else {
                  days_from_reading = 0;
                }
                
                start_reading = sector.start_reading + (sector.daily_usage * days_from_reading);
                end_reading = start_reading + sub.usage_in_period;
              }
              
              period_sectors.push({
                sector_id: sector.sector_id,
                sub_id: sub.sub_id,
                start_date: sub.start_date,
                end_date: sub.end_date,
                start_reading: start_reading,
                end_reading: end_reading,
                total_usage: sub.usage_in_period,
                days_in_period: sub.days_in_period,
                usage_in_period: sub.usage_in_period,
                daily_usage: sector.daily_usage
              });
            } else {
              const days_from_sector_start = days_between(sector.start_date, sub.start_date);
              const start_reading = sector.start_reading + (sector.daily_usage * days_from_sector_start);
              const end_reading = start_reading + sub.usage_in_period;
              
              period_sectors.push({
                sector_id: sector.sector_id,
                sub_id: sub.sub_id,
                start_date: sub.start_date,
                end_date: sub.end_date,
                start_reading: start_reading,
                end_reading: end_reading,
                total_usage: sub.usage_in_period,
                days_in_period: sub.days_in_period,
                usage_in_period: sub.usage_in_period,
                daily_usage: sector.daily_usage
              });
            }
          }
        });
      }
    });
    
    return period_sectors;
  }

  function recalculate_period_from_sectors(period_index){
    const p = periods[period_index];
    const period_sectors = get_sectors_for_period(period_index);
    
    if(period_sectors.length === 0) return;
    
    let total_usage = 0;
    let total_days = 0;
    
    period_sectors.forEach(s => {
      total_usage += (s.usage_in_period ?? s.total_usage ?? s.sector_usage ?? 0);
      total_days += (s.days_in_period ?? s.sector_days ?? 0);
    });
    
    const end_display = new Date(p.end);
    end_display.setDate(end_display.getDate() - 1);
    const period_days = days_between(p.start, end_display);
    
    // Preserve original PROVISIONAL usage before recalculating
    // This must happen BEFORE we update p.usage
    // Check if it's null AND if usage exists (regardless of status, as status might be changing)
    if(p.original_provisional_usage === null && p.usage !== null && p.usage !== undefined){
      // Preserve the current usage value before it gets overwritten
      p.original_provisional_usage = p.usage;
    }
    
    // DEFENSIVE CHECK: Prevent negative usage
    if(total_usage < 0) {
      throw new Error(`INVALID: Period usage cannot be negative (${total_usage} L). One or more readings are invalid. Please correct the reading values.`);
    }
    
    p.usage = total_usage;
    p.dailyUsage = total_usage / period_days;
    
    if(period_index === 0){
      p.closing = (p.start_reading || 0) + total_usage;
    } else {
      const prev = periods[period_index - 1];
      p.opening = prev.closing;
      p.closing = (p.opening || 0) + total_usage;
    }
    
    // DEFENSIVE CHECK: Ensure closing reading is not less than opening
    if(period_index > 0 && p.opening !== null && p.closing !== null && p.closing < p.opening) {
      throw new Error(`INVALID: Period closing reading (${p.closing} L) cannot be less than opening reading (${p.opening} L). Usage is negative. Please correct the reading values.`);
    }
    
    p.status = "CALCULATED";
    p.sectors = get_sectors_for_period(period_index);
  }

  function calculate(){
    try {
      periods.forEach((p, idx) => {
        const readings = p.readings
          .filter(r=>r.date&&r.value!==null)
          .map(r=>({d:new Date(r.date),v:r.value}))
          .sort((a,b)=>a.d-b.d);
        
        if(idx === 0){
          if(readings.length >= 1) p.start_reading = readings[0].v;
        }
      });
      
      const all_sectors = create_sectors_from_readings();
      
      periods.forEach((p, period_idx) => {
        if(p.status === "CALCULATED" || p.status === "ACTUAL"){
          return;
        }
        
        const all_readings = get_all_readings_sorted();
        // Compare Date objects: p.end is exclusive (start of next period at 00:00:00)
        // A reading on or after p.end should trigger CALCULATED status
        const periodEndDate = new Date(p.end);
        periodEndDate.setHours(0, 0, 0, 0); // Normalize to start of day
        const reading_after_period_end = all_readings.find(r => {
          const readingDate = new Date(r.date);
          readingDate.setHours(12, 0, 0, 0); // Normalize reading date to noon for comparison
          return readingDate >= periodEndDate; // >= because p.end is exclusive
        });
        
        if(reading_after_period_end && (p.status === "PROVISIONAL" || p.status === "OPEN")){
          recalculate_period_from_sectors(period_idx);
        }
      });
      
      periods.forEach((p, idx) => {
        if(idx === 0){
        } else {
          const prev = periods[idx - 1];
          p.opening = prev.closing;
        }
      });
      
      periods.forEach((p, period_idx) => {
        const readings = p.readings
          .filter(r=>r.date&&r.value!==null)
          .map(r=>({d:new Date(r.date),v:r.value}))
          .sort((a,b)=>a.d-b.d);
        
        const end_display = new Date(p.end);
        end_display.setDate(end_display.getDate()-1);
        const period_days = days_between(p.start,end_display);
        
        // Check if reading exists on period end date (actual last day, not exclusive end)
        // Normalize dates to 12:00:00 for consistent comparison
        const end_display_normalized = new Date(end_display);
        end_display_normalized.setHours(12, 0, 0, 0);
        const reading_on_period_end = readings.find(r => {
          const readingDate = new Date(r.d);
          readingDate.setHours(12, 0, 0, 0);
          return readingDate.getTime() === end_display_normalized.getTime();
        });
        const all_readings = get_all_readings_sorted();
        // Compare Date objects: p.end is exclusive (start of next period at 00:00:00)
        // A reading on or after p.end should trigger CALCULATED status
        const periodEndDate = new Date(p.end);
        periodEndDate.setHours(0, 0, 0, 0); // Normalize to start of day
        const reading_after_period_end = all_readings.find(r => {
          const readingDate = new Date(r.date);
          readingDate.setHours(12, 0, 0, 0); // Normalize reading date to noon for comparison
          return readingDate >= periodEndDate; // >= because p.end is exclusive
        });
        
        if(reading_on_period_end){
          if(period_idx === 0){
            if(readings.length >= 2){
              const r_last = readings.at(-1);
              const days = days_between(readings[0].d, r_last.d);
              if(days > 0){
                // DEFENSIVE CHECK: Prevent negative usage
                if(r_last.v <= (p.start_reading || 0)) {
                  throw new Error(`INVALID: Reading ${r_last.v} L on ${iso(r_last.d)} is lower than or equal to start reading ${p.start_reading} L. Usage cannot be negative. Please correct the reading value.`);
                }
                p.dailyUsage = (r_last.v - p.start_reading) / days;
                p.usage = p.dailyUsage * period_days;
                // DEFENSIVE CHECK: Ensure usage is not negative
                if(p.usage < 0) {
                  throw new Error(`INVALID: Period usage cannot be negative (${p.usage} L). Reading ${r_last.v} L is too low for start reading ${p.start_reading} L. Please correct the reading value.`);
                }
                p.closing = p.start_reading + p.usage;
                p.status = "ACTUAL";
              }
            }
          } else {
            if(readings.length >= 1){
              const r_last = readings.at(-1);
              const co_reading_date = new Date(p.start);
              co_reading_date.setHours(12, 0, 0, 0);
              const last_reading_date = new Date(r_last.d);
              last_reading_date.setHours(12, 0, 0, 0);
              const days = days_between(co_reading_date, last_reading_date);
              if(days > 0){
                // DEFENSIVE CHECK: Prevent negative usage
                if(r_last.v <= (p.opening || 0)) {
                  throw new Error(`INVALID: Reading ${r_last.v} L on ${iso(r_last.d)} is lower than or equal to period opening reading ${p.opening} L. Usage cannot be negative. Please correct the reading value.`);
                }
                p.dailyUsage = (r_last.v - p.opening) / days;
                p.usage = p.dailyUsage * period_days;
                // DEFENSIVE CHECK: Ensure usage is not negative
                if(p.usage < 0) {
                  throw new Error(`INVALID: Period usage cannot be negative (${p.usage} L). Reading ${r_last.v} L is too low for period opening ${p.opening} L. Please correct the reading value.`);
                }
                p.closing = p.opening + p.usage;
                p.status = "ACTUAL";
              }
            }
          }
          p.sectors = get_sectors_for_period(period_idx);
        } else if(reading_after_period_end){
          if(p.status === "PROVISIONAL" || p.status === "OPEN"){
            recalculate_period_from_sectors(period_idx);
          } else {
            p.sectors = get_sectors_for_period(period_idx);
          }
        } else {
          if(period_idx === 0){
            if(readings.length >= 2){
              const r_last = readings.at(-1);
              const days = days_between(readings[0].d, r_last.d);
              if(days > 0){
                // DEFENSIVE CHECK: Prevent negative usage
                if(r_last.v <= (p.start_reading || 0)) {
                  throw new Error(`INVALID: Reading ${r_last.v} L on ${iso(r_last.d)} is lower than or equal to start reading ${p.start_reading} L. Usage cannot be negative. Please correct the reading value.`);
                }
                p.dailyUsage = (r_last.v - p.start_reading) / days;
                p.usage = p.dailyUsage * period_days;
                // DEFENSIVE CHECK: Ensure usage is not negative
                if(p.usage < 0) {
                  throw new Error(`INVALID: Period usage cannot be negative (${p.usage} L). Reading ${r_last.v} L is too low for start reading ${p.start_reading} L. Please correct the reading value.`);
                }
                p.closing = p.start_reading + p.usage;
                // Check against end_display (actual last day), not p.end (exclusive)
                const lastReadingDate = new Date(r_last.d);
                lastReadingDate.setHours(12, 0, 0, 0);
                const endDisplayNorm = new Date(end_display);
                endDisplayNorm.setHours(12, 0, 0, 0);
                p.status = lastReadingDate.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
                if(p.status === "PROVISIONAL" && p.original_provisional_usage === null){
                  p.original_provisional_usage = p.usage;
                }
              }
            } else if(readings.length === 1){
              const r1 = readings[0];
              const days = days_between(p.start, r1.d);
              if(days > 0){
                // DEFENSIVE CHECK: Prevent negative usage
                if(r1.v <= (p.start_reading || 0)) {
                  throw new Error(`INVALID: Reading ${r1.v} L on ${iso(r1.d)} is lower than or equal to start reading ${p.start_reading} L. Usage cannot be negative. Please correct the reading value.`);
                }
                p.dailyUsage = (r1.v - p.start_reading) / days;
                p.usage = p.dailyUsage * period_days;
                // DEFENSIVE CHECK: Ensure usage is not negative
                if(p.usage < 0) {
                  throw new Error(`INVALID: Period usage cannot be negative (${p.usage} L). Reading ${r1.v} L is too low for start reading ${p.start_reading} L. Please correct the reading value.`);
                }
                p.closing = p.start_reading + p.usage;
                p.status = "PROVISIONAL";
                if(p.original_provisional_usage === null){
                  p.original_provisional_usage = p.usage;
                }
              }
            }
          } else {
            if(readings.length >= 1){
              const r_last = readings.at(-1);
              const co_reading_date = new Date(p.start);
              co_reading_date.setHours(12, 0, 0, 0);
              const last_reading_date = new Date(r_last.d);
              last_reading_date.setHours(12, 0, 0, 0);
              const days = days_between(co_reading_date, last_reading_date);
              if(days > 0){
                // DEFENSIVE CHECK: Prevent negative usage
                if(r_last.v <= (p.opening || 0)) {
                  throw new Error(`INVALID: Reading ${r_last.v} L on ${iso(r_last.d)} is lower than or equal to period opening reading ${p.opening} L. Usage cannot be negative. Please correct the reading value.`);
                }
                p.dailyUsage = (r_last.v - p.opening) / days;
                p.usage = p.dailyUsage * period_days;
                // DEFENSIVE CHECK: Ensure usage is not negative
                if(p.usage < 0) {
                  throw new Error(`INVALID: Period usage cannot be negative (${p.usage} L). Reading ${r_last.v} L is too low for period opening ${p.opening} L. Please correct the reading value.`);
                }
                p.closing = p.opening + p.usage;
                // Check against end_display (actual last day), not p.end (exclusive)
                const lastReadingDateNorm = new Date(r_last.d);
                lastReadingDateNorm.setHours(12, 0, 0, 0);
                const endDisplayNorm = new Date(end_display);
                endDisplayNorm.setHours(12, 0, 0, 0);
                p.status = lastReadingDateNorm.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
                if(p.status === "PROVISIONAL" && p.original_provisional_usage === null){
                  p.original_provisional_usage = p.usage;
                }
              }
            } else {
              const prev_period = periods[period_idx - 1];
              if (prev_period && prev_period.dailyUsage !== null && prev_period.dailyUsage !== undefined && prev_period.dailyUsage > 0) {
                p.dailyUsage = prev_period.dailyUsage;
                p.usage = p.dailyUsage * period_days;
                p.closing = p.opening + p.usage;
                p.status = "PROVISIONAL";
                if(p.original_provisional_usage === null){
                  p.original_provisional_usage = p.usage;
                }
              }
            }
          }
          p.sectors = get_sectors_for_period(period_idx);
        }
      });
    } catch (error) {
      throw new Error("Error in calculate: " + error.message);
    }
  }

  function add_period(){
    try {
      const bill_day_el = document.getElementById("bill_day");
      const start_month_el = document.getElementById("start_month");
      
      if (!bill_day_el) {
        throw new Error("bill_day element not found");
      }
      if (!start_month_el) {
        throw new Error("start_month element not found");
      }
      
      const bill_day = Number(bill_day_el.value);
      const start_month_value = start_month_el.value;
      
      if (!start_month_value) {
        throw new Error("start_month value is empty");
      }
      
      const [year_str, month_str] = start_month_value.split("-");
      const start_year = Number(year_str);
      const start_month = Number(month_str);
      
      let start, end;
      
      if(periods.length === 0){
        // First period: start on current month's bill day (inclusive), end on next month's bill day (exclusive)
        start = new Date(start_year, start_month - 1, bill_day, 12, 0, 0);
        end = new Date(start_year, start_month, bill_day, 12, 0, 0);
      } else {
        // Subsequent periods: start where previous ended, end one month later on bill day
        const prev_period = periods[periods.length - 1];
        if (!prev_period || !prev_period.end) {
          throw new Error("Previous period missing end date");
        }
        // Create new Date objects (don't mutate the previous period's dates)
        start = new Date(prev_period.end);
        end = new Date(start);
        end.setMonth(end.getMonth() + 1);
        end.setDate(bill_day);
        end.setHours(12, 0, 0);
      }
      
      periods.push({
        start,
        end,
        status: "PROVISIONAL",
        readings: [],
        opening: null,
        closing: null,
        usage: null,
        dailyUsage: null,
        original_provisional_usage: null,
        sectors: []
      });
      
      // Set the newly added period as active
      active = periods.length - 1;
    } catch (error) {
      throw new Error("Error in add_period: " + error.message);
    }
  }

  function add_reading(){
    try {
      if(active === null) {
        throw new Error("No active period");
      }
      periods[active].readings.push({ date:null, value:null });
    } catch (error) {
      throw new Error("Error in add_reading: " + error.message);
    }
  }

  // ==================== PUBLIC API ====================
  return {
    // State Management
    getPeriods: function() { return periods; },
    setPeriods: function(newPeriods) { periods = newPeriods; },
    getActive: function() { return active; },
    setActive: function(newActive) { active = newActive; },
    getDebugCurrentPeriodIndex: function() { return debug_current_period_index; },
    setDebugCurrentPeriodIndex: function(idx) { debug_current_period_index = idx; },
    
    // Helper Functions (exposed for UI use)
    format_number: format_number,
    format_date: format_date,
    format_date_range: format_date_range,
    format_period_display: format_period_display,
    iso: iso,
    days_between: days_between,
    get_tiers: get_tiers,
    
    // Core Calculations
    calculate: calculate,
    recalculate_period_from_sectors: recalculate_period_from_sectors,
    
    // Sector System
    get_all_readings_sorted: get_all_readings_sorted,
    create_sectors_from_readings: create_sectors_from_readings,
    split_sector_at_period_boundaries: split_sector_at_period_boundaries,
    get_sectors_for_period: get_sectors_for_period,
    
    // Tier Calculations
    calculate_tier_cost_for_litres: calculate_tier_cost_for_litres,
    calculate_reconciliation_tier_cost: calculate_reconciliation_tier_cost,
    
    // Data Operations
    add_period: add_period,
    add_reading: add_reading,
    
    // Integrity Verification
    verifyIntegrity: function() {
      const requiredMethods = [
        'calculate', 'recalculate_period_from_sectors', 'get_all_readings_sorted',
        'create_sectors_from_readings', 'get_sectors_for_period', 'add_period', 'add_reading'
      ];
      const missing = requiredMethods.filter(m => typeof this[m] !== 'function');
      if(missing.length > 0) {
        throw new Error('BillingEngineLogic integrity check failed: Missing methods: ' + missing.join(', '));
      }
      return { valid: true, checksum: 'BillingEngineLogic_v1' };
    }
  };
})();

// @END_PROTECTED_MODULE: BillingEngineLogic

// @PROTECTED_MODULE: BillingEngineUI
// This container encapsulates ALL UI rendering, display, interaction, and revision tracking
// CRITICAL: All UI functions, rendering, and user interactions are contained here
// This module can be extracted and reused in Laravel Blade + Vue applications

const BillingEngineUI = (function() {
  'use strict';
  
  // ==================== PRIVATE STATE ====================
  let revisionNumber = 0;
  let revisions = [];
  let contextMenu = null;
  
  // ==================== PRIVATE HELPER FUNCTIONS ====================
  
  function log_error(msg) {
    console.error(msg);
    const errorDiv = document.getElementById("errors");
    if (errorDiv) {
      errorDiv.innerHTML = '<div class="error">ERROR: ' + msg + '</div>';
    }
  }

  function update_revision_data_in_html() {
    const revisionScript = document.getElementById('revision_data');
    if (revisionScript) {
      revisionScript.textContent = JSON.stringify({
        revisions: revisions,
        revisionNumber: revisionNumber
      }, null, 2);
    }
  }

  // ==================== PUBLIC API ====================
  return {
    // Revision Tracking
    init_revision_system: function() {
      const revisionScript = document.getElementById('revision_data');
      if (revisionScript) {
        try {
          const data = JSON.parse(revisionScript.textContent);
          revisions = data.revisions || [];
          revisionNumber = data.revisionNumber || 0;
        } catch (e) {
          console.warn('Could not parse revision data, starting fresh:', e);
          revisions = [];
          revisionNumber = 0;
        }
      }
      this.render_revision_history();
    },
    
    save_revision: function(action, details) {
      revisionNumber++;
      const revision = {
        number: revisionNumber,
        timestamp: new Date().toISOString(),
        action: action,
        details: details
      };
      revisions.push(revision);
      
      if (revisions.length > 50) {
        revisions = revisions.slice(-50);
      }
      
      update_revision_data_in_html();
      this.render_revision_history();
    },
    
    render_revision_history: function() {
      const container = document.getElementById('revision_history');
      if (!container) return;
      
      if (revisions.length === 0) {
        container.innerHTML = '<div style="color: #6c757d; font-style: italic;">No revisions yet</div>';
        return;
      }
      
      let html = '';
      const recentRevisions = revisions.slice(-10).reverse();
      recentRevisions.forEach(rev => {
        const date = new Date(rev.timestamp);
        const timeStr = date.toLocaleString();
        html += `<div class="revision-item">
          <span class="revision-number">Rev ${rev.number}</span> - 
          <span class="revision-action">${rev.action}</span> - 
          <span class="revision-timestamp">${timeStr}</span>
          ${rev.details ? `<div style="margin-top: 4px; font-size: 11px; color: #6c757d;">${rev.details}</div>` : ''}
        </div>`;
      });
      container.innerHTML = html;
    },
    
    clear_revision_history: function() {
      if (confirm('Clear all revision history?')) {
        revisions = [];
        revisionNumber = 0;
        update_revision_data_in_html();
        this.render_revision_history();
      }
    },
    
    save_html_with_revisions: function() {
      try {
        // Increment revision number before saving (this will be saved in the revision entry)
        revisionNumber++;
        const currentRev = revisionNumber;
        
        // Update the revision data in the HTML before saving
        update_revision_data_in_html();
        
        // Get the current HTML content
        const htmlContent = document.documentElement.outerHTML;
        
        // Create a blob and download it with new naming convention
        const blob = new Blob([htmlContent], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `MyCities - Billing - Rev_${currentRev}.html`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        // Save revision entry for the save action (this will increment again, so we use currentRev)
        const revision = {
          number: currentRev,
          timestamp: new Date().toISOString(),
          action: 'HTML Saved',
          details: `HTML file saved as: MyCities - Billing - Rev_${currentRev}.html`
        };
        revisions.push(revision);
        
        if (revisions.length > 50) {
          revisions = revisions.slice(-50);
        }
        
        update_revision_data_in_html();
        this.render_revision_history();
        
        alert(`HTML file saved as: MyCities - Billing - Rev_${currentRev}.html`);
      } catch (error) {
        log_error('Failed to save HTML: ' + error.message);
        console.error('Error saving HTML:', error);
      }
    },
    
    copy_input_history: function() {
      try {
        if (revisions.length === 0) {
          alert('No input history to copy');
          return;
        }
        
        let text = '=== INPUT HISTORY ===\n\n';
        revisions.forEach(rev => {
          const date = new Date(rev.timestamp);
          const timeStr = date.toLocaleString();
          text += `Rev ${rev.number} - ${rev.action} - ${timeStr}\n`;
          if (rev.details) {
            text += `  ${rev.details}\n`;
          }
          text += '\n';
        });
        
        navigator.clipboard.writeText(text).then(() => {
          alert('Input history copied to clipboard!');
        }).catch(err => {
          log_error('Failed to copy input history: ' + err.message);
        });
      } catch (error) {
        log_error('Error copying input history: ' + error.message);
        console.error('Error in copy_input_history:', error);
      }
    },
    
    // Error Handling
    log_error: log_error,
    
    // Context Menu
    init_context_menu: function() {
      contextMenu = document.getElementById('context_menu');
      if (!contextMenu) return;
      
      document.addEventListener('click', (e) => {
        if (!contextMenu.contains(e.target)) {
          contextMenu.style.display = 'none';
        }
      });
      
      document.addEventListener('contextmenu', (e) => {
        const field = e.target.closest('.calculable-field');
        if (field) {
          e.preventDefault();
          this.show_calculation_explanation(field, e);
        }
      });
    },
    
    show_calculation_explanation: function(fieldElement, event) {
      const fieldName = fieldElement.getAttribute('data-field');
      const calculationExplanations = window.calculationExplanations || {};
      const explanation = calculationExplanations[fieldName];
      
      if (!explanation || !contextMenu) return;
      
      const header = document.getElementById('context_menu_header');
      const content = document.getElementById('context_menu_explanation');
      
      if (!header || !content) return;
      
      header.textContent = explanation.title;
      
      let html = `<div style="margin-bottom: 10px;"><strong>Description:</strong><br>${explanation.description}</div>`;
      
      if (explanation.formula) {
        html += `<div class="context-menu-formula"><strong>Formula:</strong><br>${explanation.formula.replace(/\n/g, '<br>')}</div>`;
      }
      
      if (explanation.example) {
        html += `<div style="margin-top: 10px;"><strong>Example:</strong><br><pre style="background: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 11px; margin-top: 5px;">${explanation.example.replace(/\n/g, '\n')}</pre></div>`;
      }
      
      content.innerHTML = html;
      
      contextMenu.style.display = 'block';
      contextMenu.style.left = event.pageX + 'px';
      contextMenu.style.top = event.pageY + 'px';
      
      setTimeout(() => {
        const rect = contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
          contextMenu.style.left = (event.pageX - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
          contextMenu.style.top = (event.pageY - rect.height) + 'px';
        }
      }, 0);
    },
    
    // Render Functions (will reference BillingEngineLogic)
    // These will be assigned after BillingEngineLogic is defined
    render: null,
    render_calculation_output: null,
    render_readings: null,
    copy_output_to_clipboard: null,
    copy_all_periods_to_clipboard: null,
    show_sector_analysis: null,
    next_period_update: null,
    render_debug: null,
    
    // Integrity Verification
    verifyIntegrity: function() {
      const requiredMethods = [
        'init_revision_system', 'save_revision', 'render_revision_history',
        'render', 'render_calculation_output', 'render_readings', 'log_error'
      ];
      const missing = requiredMethods.filter(m => typeof this[m] !== 'function');
      if(missing.length > 0) {
        throw new Error('BillingEngineUI integrity check failed: Missing methods: ' + missing.join(', '));
      }
      return { valid: true, checksum: 'BillingEngineUI_v1' };
    }
  };
})();

// Assign render functions to UI container after both containers are defined
// These functions need access to both containers
(function assignRenderFunctions() {
  const Logic = BillingEngineLogic;
  const UI = BillingEngineUI;
  
  UI.render = function() {
    try {
      const periods = Logic.getPeriods();
      const active = Logic.getActive();
      const pt = document.getElementById("period_table");
      if (!pt) {
        // Element not found - likely in Date to Date mode, skip Period to Period rendering
        return;
      }
      
      pt.innerHTML = "<tr><th>#</th><th>Billing Period</th><th>Status</th><th>Period_Total_Usage (L)</th></tr>";
      
      // NOTE: Period 2+ computation is now handled by PHP backend (BillingCalculatorPeriodToPeriod::calculatePeriod2Plus)
      // JavaScript should call PHP API instead of computing locally
      
      periods.forEach((p,i)=>{
        const tr = document.createElement("tr");
        if(i===active) tr.classList.add("ACTIVE");
        tr.onclick = ()=>{ 
          Logic.setActive(i); 
          this.render(); 
        };

        const period_display = Logic.format_period_display(p);
        const status_class = p.status === "ACTUAL" ? "ACTUAL" : 
                           p.status === "PROVISIONAL" ? "PROVISIONAL" : 
                           p.status === "CALCULATED" ? "CALCULATED" : "PROVISIONAL";
        
        let usage_display = "—";
        if(p.usage !== null && p.usage !== undefined){
          if(p.status === "CALCULATED" && p.original_provisional_usage !== null && p.original_provisional_usage !== undefined){
            usage_display = `${p.original_provisional_usage.toFixed(0)} (${p.usage.toFixed(0)})`;
          } else {
            usage_display = p.usage.toFixed(0);
          }
        }
        
        tr.innerHTML = `
          <td>${i+1}</td>
          <td>${period_display}</td>
          <td><span class="badge ${status_class}">${p.status || "PROVISIONAL"}</span></td>
          <td>${usage_display}</td>
        `;
        pt.appendChild(tr);
      });

      // Call legacy render functions (they will be moved to container later)
      if(typeof render_readings === 'function') {
        render_readings();
      }
      
      // Update dashboard for Period to Period mode
      if(typeof this.updatePeriodDashboard === 'function') {
        this.updatePeriodDashboard();
      }
      
      if (periods[active] && periods[active].usage !== undefined) {
        if(typeof render_calculation_output === 'function') {
          render_calculation_output();
        }
      } else {
        if(typeof render_debug === 'function') {
          render_debug();
        }
      }
    } catch (error) {
      this.log_error(error.message);
      console.error("Error in render:", error);
    }
  };
  
  // Update Period Dashboard - similar to SectorBillingUI.updateDashboard
  UI.updatePeriodDashboard = function() {
    try {
      const dashboardEl = document.getElementById('period_dashboard');
      if (!dashboardEl) return; // Dashboard not found - might be in Date to Date mode
      
      const periods = Logic.getPeriods();
      const active = Logic.getActive();
      
      if (active !== null && periods[active]) {
        const p = periods[active];
        
        // Calculate period days (end is exclusive, so subtract 1 day)
        const period_end_display = new Date(p.end);
        period_end_display.setDate(period_end_display.getDate() - 1);
        const period_days = Logic.days_between ? Logic.days_between(p.start, period_end_display) : days_between(p.start, period_end_display);
        
        // Calculate usage metrics
        const totalUsage = p.usage || 0;
        const dailyUsage = p.dailyUsage || (period_days > 0 ? totalUsage / period_days : 0);
        
        // Calculate cost from tier charges (same logic as render_calculation_output)
        const tiers = Logic.get_tiers ? Logic.get_tiers() : get_tiers();
        let remaining = totalUsage;
        let prev = 0;
        let totalCost = 0;
        
        for(const t of tiers){
          const cap = t.max - prev;
          const used = Math.max(0, Math.min(remaining, cap));
          if(used > 0){
            const cost = (used / 1000) * t.rate;
            totalCost += cost;
            remaining -= used;
          }
          prev = t.max;
        }
        
        const dailyCost = period_days > 0 ? totalCost / period_days : 0;
        
        // Format numbers using Logic's format_number or global format_number
        const formatNum = Logic.format_number || format_number;
        
        // Update dashboard elements
        const dailyUsageEl = document.getElementById('period_dashboard_daily_usage');
        const dailyCostEl = document.getElementById('period_dashboard_daily_cost');
        const totalUsedEl = document.getElementById('period_dashboard_total_used');
        const totalCostEl = document.getElementById('period_dashboard_total_cost');
        
        if (dailyUsageEl) {
          dailyUsageEl.textContent = totalUsage > 0 ? formatNum(dailyUsage) + ' L' : '—';
        }
        if (dailyCostEl) {
          dailyCostEl.textContent = totalCost > 0 ? 'R' + dailyCost.toFixed(2) : '—';
        }
        if (totalUsedEl) {
          totalUsedEl.textContent = totalUsage > 0 ? formatNum(totalUsage) + ' L' : '—';
        }
        if (totalCostEl) {
          totalCostEl.textContent = totalCost > 0 ? 'R ' + totalCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") : 'R 0.00';
        }
        
        // Make sure dashboard is visible
        dashboardEl.style.display = 'block';
      } else {
        // No active period - show dashes
        const dailyUsageEl = document.getElementById('period_dashboard_daily_usage');
        const dailyCostEl = document.getElementById('period_dashboard_daily_cost');
        const totalUsedEl = document.getElementById('period_dashboard_total_used');
        const totalCostEl = document.getElementById('period_dashboard_total_cost');
        
        if (dailyUsageEl) dailyUsageEl.textContent = '—';
        if (dailyCostEl) dailyCostEl.textContent = '—';
        if (totalUsedEl) totalUsedEl.textContent = '—';
        if (totalCostEl) totalCostEl.textContent = 'R 0.00';
        
        // Make sure dashboard is visible even when no period is active
        dashboardEl.style.display = 'block';
      }
    } catch (error) {
      console.error('Error updating period dashboard:', error);
    }
  };
  
  // Additional render functions will be assigned here
  // For now, they remain as legacy functions that will be moved incrementally
})();

// @END_PROTECTED_MODULE: BillingEngineUI

/* ==================== LEGACY CODE (TO BE MOVED TO CONTAINERS) ==================== */
// This section will be gradually moved into the containers above

let revisionNumber = 0;
let revisions = [];
let debug_current_period_index = null;  // For step-by-step debugging

/* ==================== REVISION TRACKING ==================== */
function init_revision_system() {
    // Load revision history from embedded script tag in HTML
    const revisionScript = document.getElementById('revision_data');
    if (revisionScript) {
        try {
            const data = JSON.parse(revisionScript.textContent);
            revisions = data.revisions || [];
            revisionNumber = data.revisionNumber || 0;
        } catch (e) {
            console.warn('Could not parse revision data, starting fresh:', e);
            revisions = [];
            revisionNumber = 0;
        }
    }
    render_revision_history();
}

function save_revision(action, details) {
    revisionNumber++;
    const revision = {
        number: revisionNumber,
        timestamp: new Date().toISOString(),
        action: action,
        details: details
    };
    revisions.push(revision);
    
    // Keep only last 50 revisions
    if (revisions.length > 50) {
        revisions = revisions.slice(-50);
    }
    
    // Update embedded script tag in HTML
    update_revision_data_in_html();
    
    render_revision_history();
}

function update_revision_data_in_html() {
    // Update the embedded script tag with current revision data
    const revisionScript = document.getElementById('revision_data');
    if (revisionScript) {
        revisionScript.textContent = JSON.stringify({
            revisions: revisions,
            revisionNumber: revisionNumber
        }, null, 2);
    }
}

function render_revision_history() {
    const container = document.getElementById('revision_history');
    if (!container) return;
    
    if (revisions.length === 0) {
        container.innerHTML = '<div style="color: #6c757d; font-style: italic;">No revisions yet</div>';
        return;
    }
    
    let html = '';
    // Show last 10 revisions (most recent first)
    const recentRevisions = revisions.slice(-10).reverse();
    recentRevisions.forEach(rev => {
        const date = new Date(rev.timestamp);
        const timeStr = date.toLocaleString();
        html += `<div class="revision-item">
            <span class="revision-number">Rev ${rev.number}</span> - 
            <span class="revision-action">${rev.action}</span> - 
            <span class="revision-timestamp">${timeStr}</span>
            ${rev.details ? `<div style="margin-top: 4px; font-size: 11px; color: #6c757d;">${rev.details}</div>` : ''}
        </div>`;
    });
    container.innerHTML = html;
}

function clear_revision_history() {
    if (confirm('Clear all revision history?')) {
        revisions = [];
        revisionNumber = 0;
        update_revision_data_in_html();
        render_revision_history();
    }
}

function save_html_with_revisions() {
    // Delegate to BillingEngineUI container
    BillingEngineUI.save_html_with_revisions();
}

function copy_input_history() {
    try {
        if (revisions.length === 0) {
            alert('No input history to copy');
            return;
        }
        
        let text = '=== INPUT HISTORY ===\n\n';
        revisions.forEach(rev => {
            const date = new Date(rev.timestamp);
            const timeStr = date.toLocaleString();
            text += `Rev ${rev.number} - ${rev.action} - ${timeStr}\n`;
            if (rev.details) {
                text += `  ${rev.details}\n`;
            }
            text += '\n';
        });
        
        navigator.clipboard.writeText(text).then(() => {
            alert('Input history copied to clipboard!');
        }).catch(err => {
            log_error('Failed to copy input history: ' + err.message);
        });
    } catch (error) {
        log_error('Error copying input history: ' + error.message);
        console.error('Error in copy_input_history:', error);
    }
}

/* ==================== TIME HELPERS ==================== */
function iso(d){ 
  return d.toISOString().slice(0,10); 
}

function format_date(d){
  const day = d.getDate();
  const suffix =
    day % 10 === 1 && day !== 11 ? "st" :
    day % 10 === 2 && day !== 12 ? "nd" :
    day % 10 === 3 && day !== 13 ? "rd" : "th";
  return `${day}${suffix} ${d.toLocaleString("en-GB",{month:"short"})} ${d.getFullYear()}`;
}

function format_date_range(start_date, end_date){
  const start = new Date(start_date);
  const end = new Date(end_date);
  const start_day = start.getDate();
  const end_day = end.getDate();
  const start_month = start.toLocaleString("en-GB",{month:"short"});
  const end_month = end.toLocaleString("en-GB",{month:"short"});
  
  if(start_month === end_month){
    return `${start_day}–${end_day} ${start_month}`;
  } else {
    return `${start_day} ${start_month}–${end_day} ${end_month}`;
  }
}

function format_number(num){
  return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

function log_error(msg) {
  console.error(msg);
  const errorDiv = document.getElementById("errors");
  if (errorDiv) {
    errorDiv.innerHTML = '<div class="error">ERROR: ' + msg + '</div>';
  }
}

/* ==================== RECONCILIATION TIER COST CALCULATION ==================== */
/**
 * Calculate tier cost for a given litreage value
 * @param {number} litres - The litreage value to calculate tier costs for
 * @returns {Object} - Object with total_cost and breakdown
 */
function calculate_tier_cost_for_litres(litres){
  if(litres === null || litres === undefined || litres <= 0){
    return { total_cost: 0, breakdown: [] };
  }
  
  const tiers = get_tiers();
  if(tiers.length === 0){
    return { total_cost: 0, breakdown: [] };
  }
  
  let remaining = litres;
  let prev = 0;
  let total_cost = 0;
  const breakdown = [];
  
  for(const t of tiers){
    const cap = t.max - prev;
    const used = Math.max(0, Math.min(remaining, cap));
    
    if(used > 0){
      const cost = (used / 1000) * t.rate;
      breakdown.push({
        prev: prev,
        max: t.max,
        used: used,
        rate: t.rate,
        cost: cost
      });
      total_cost += cost;
      remaining -= used;
    }
    
    prev = t.max;
    if(remaining <= 0) break;
  }
  
  if(remaining > 0 && tiers.length > 0){
    const last_tier = tiers[tiers.length - 1];
    const cost = (remaining / 1000) * last_tier.rate;
    breakdown.push({
      prev: prev,
      max: Infinity,
      used: remaining,
      rate: last_tier.rate,
      cost: cost
    });
    total_cost += cost;
  }
  
  return { total_cost: total_cost, breakdown: breakdown };
}

/**
 * Calculate tier cost reconciliation between PROVISIONED and CALCULATED usage amounts
 * @param {number} adjustment_litres - The reconciliation amount in litres (can be positive or negative)
 * @param {number} provisioned_usage - The original PROVISIONED usage amount in litres
 * @param {number} calculated_usage - The CALCULATED usage amount in litres
 * @returns {Object|null} - Object with total_cost, breakdown, and calculation details, or null if no adjustment
 */
function calculate_reconciliation_tier_cost(adjustment_litres, provisioned_usage, calculated_usage){
  if(adjustment_litres === null || adjustment_litres === undefined || adjustment_litres === 0){
    return null;
  }
  
  if(provisioned_usage === null || provisioned_usage === undefined || 
     calculated_usage === null || calculated_usage === undefined){
    return null;
  }
  
  // Step 1: Calculate tier cost for PROVISIONED (original - to be credited)
  const provisioned_cost = calculate_tier_cost_for_litres(provisioned_usage);
  
  // Step 2: Calculate tier cost for CALCULATED (corrected - to be charged)
  const calculated_cost = calculate_tier_cost_for_litres(calculated_usage);
  
  // Step 3: Reconciliation cost = Calculated cost - Provisioned cost
  const reconciliation_cost = calculated_cost.total_cost - provisioned_cost.total_cost;
  
  return {
    total_cost: reconciliation_cost,
    provisioned_cost: provisioned_cost.total_cost,
    calculated_cost: calculated_cost.total_cost,
    provisioned_breakdown: provisioned_cost.breakdown,
    calculated_breakdown: calculated_cost.breakdown,
    adjustment_litres: adjustment_litres,
    provisioned_litres: provisioned_usage,
    calculated_litres: calculated_usage
  };
}

/* ==================== PROTECTED: PERIOD CALCULATION FUNCTION ==================== */
// @PROTECTED_MODULE: addPeriod_function
function add_period(){
  try {
    const bill_day_el = document.getElementById("bill_day");
    const start_month_el = document.getElementById("start_month");
    
    if (!bill_day_el) {
      log_error("bill_day element not found");
      return;
    }
    if (!start_month_el) {
      log_error("start_month element not found");
      return;
    }
    
    const bill_day = Number(bill_day_el.value);
    const start_month_value = start_month_el.value;
    
    if (!start_month_value) {
      log_error("start_month value is empty");
      return;
    }
    
    const parts = start_month_value.split("-");
    if (parts.length !== 2) {
      log_error("Invalid start_month format: " + start_month_value);
      return;
    }
    
    const y = Number(parts[0]);
    const m = Number(parts[1]);
    
    let start, end;

    if(periods.length === 0){
      // First period: start on current month's bill day (inclusive), end on next month's bill day (exclusive)
      start = new Date(y, m-1, bill_day, 12, 0, 0);
      end = new Date(y, m, bill_day, 12, 0, 0);
    } else {
      // Subsequent periods: start where previous ended, end one month later on bill day
      const prev_period = periods[periods.length-1];
      if (!prev_period || !prev_period.end) {
        log_error("Previous period missing end date");
        return;
      }
      // Create new Date objects (don't mutate the previous period's dates)
      start = new Date(prev_period.end);
      end = new Date(start);
      end.setMonth(end.getMonth() + 1);
      end.setDate(bill_day);
      end.setHours(12, 0, 0);
    }

    periods.push({
      start,
      end,
      status: "PROVISIONAL",
      readings: [],
      opening: null,
      closing: null,
      usage: null,
      dailyUsage: null,
      original_provisional_usage: null,  // Original PROVISIONAL usage value (for visual tracking)
      sectors: []                  // Sectors contributing to this period
    });

    // Set the newly added period as active
    active = periods.length - 1;
    console.log("Period " + periods.length + " added. Active index: " + active);
    
    // Track revision
    save_revision('Period Added', `Period ${periods.length}: ${format_date(start)} to ${format_date(end)}`);
    
    render();
  } catch (error) {
    log_error(error.message);
    console.error("Error in add_period:", error);
  }
}
// @END_PROTECTED_MODULE: addPeriod_function

/* ==================== UI MODULE: PERIOD DISPLAY LOGIC ==================== */
// @PROTECTED_MODULE: UI_Rev1
function format_period_display(period) {
  // Display: "20th Jan → 19th Feb" (showing 20th to 19th for period 20th to 20th exclusive)
  // Logic: end date is exclusive, so subtract 1 day for display
  const end_display = new Date(period.end);
  end_display.setDate(end_display.getDate() - 1); // Show the actual last day of period
  return `${format_date(period.start)} -&gt; ${format_date(end_display)}`;
}

/* ==================== UI MODULE: RENDER ==================== */
function render(){
  try {
    const pt = document.getElementById("period_table");
    if (!pt) {
      // Element not found - likely in Date to Date mode, skip Period to Period rendering
      return;
    }
    
    pt.innerHTML = "<tr><th>#</th><th>Billing Period</th><th>Status</th><th>Period_Total_Usage (L)</th></tr>";

    // Auto-calculate all periods that need it BEFORE building the table
    periods.forEach((p, i) => {
      if (i > 0) { // Period 2+
        const readings = p.readings.filter(r => r.date && r.value !== null);
        
        if (readings.length === 0 && (p.usage === null || p.usage === undefined)) {
          const prev_period = periods[i - 1];
          if (prev_period && prev_period.dailyUsage !== null && prev_period.dailyUsage !== undefined && prev_period.dailyUsage > 0) {
            // Set opening reading from previous period's closing reading
            const prev_closing = prev_period.closing;
            if (prev_closing !== null && prev_closing !== undefined) {
              p.opening = prev_closing;
            } else {
              p.opening = 0;
            }
            
            // Auto-calculate using previous period's Daily_Usage
            const opening_reading_value = p.opening || 0;
            const period_end_display = new Date(p.end);
            period_end_display.setDate(period_end_display.getDate() - 1);
            const period_days = days_between(p.start, period_end_display);
            
            p.dailyUsage = prev_period.dailyUsage;
            p.usage = p.dailyUsage * period_days;
            p.closing = opening_reading_value + p.usage;
            p.status = "PROVISIONAL";
            p.sectors = get_sectors_for_period(i);
          }
        }
      }
    });

    periods.forEach((p,i)=>{
      const tr = document.createElement("tr");
      if(i===active) tr.classList.add("ACTIVE");
      tr.onclick = ()=>{ active=i; render(); };

      const period_display = format_period_display(p);
      const status_class = p.status === "ACTUAL" ? "ACTUAL" : 
                         p.status === "PROVISIONAL" ? "PROVISIONAL" : 
                         p.status === "CALCULATED" ? "CALCULATED" : "PROVISIONAL";
      
      // Format usage display: for CALCULATED periods with original_provisional_usage, show original (new)
      let usage_display = "—";
      if(p.usage !== null && p.usage !== undefined){
        if(p.status === "CALCULATED" && p.original_provisional_usage !== null && p.original_provisional_usage !== undefined){
          // Show original PROVISIONAL value and new CALCULATED value
          usage_display = `${p.original_provisional_usage.toFixed(0)} (${p.usage.toFixed(0)})`;
        } else {
          usage_display = p.usage.toFixed(0);
        }
      }
      
      tr.innerHTML = `
        <td>${i+1}</td>
        <td>${period_display}</td>
        <td><span class="badge ${status_class}">${p.status || "PROVISIONAL"}</span></td>
        <td>${usage_display}</td>
      `;
      pt.appendChild(tr);
    });

    render_readings();
    
    if (periods[active] && periods[active].usage !== undefined) {
      render_calculation_output();
    } else {
      render_debug();
    }
  } catch (error) {
    log_error(error.message);
    console.error("Error in render:", error);
  }
}

/* ==================== TIER CONFIGURATION ==================== */
function get_tiers(){
  // Must use template tiers - no fallback to table
  if (typeof currentTemplateTiers !== 'undefined' && currentTemplateTiers !== null && currentTemplateTiers.length > 0) {
    return currentTemplateTiers;
  }
  
  // No template selected - return empty
  console.error("No tariff template selected. Please select a tariff template first.");
  return [];
}

/* ==================== READINGS ==================== */
function add_reading(){
  try {
    if(active === null) {
      log_error("No active period");
      return;
    }
    periods[active].readings.push({ date:null, value:null });
    
    // Track revision
    save_revision('Reading Added', `Period ${active + 1}: New reading row added`);
    
    render();
  } catch (error) {
    log_error(error.message);
    console.error("Error in add_reading:", error);
  }
}

/* ==================== UI MODULE: RENDER READINGS ==================== */
function render_readings(){
  try {
    // Check for both table IDs (legacy and new)
    let rt = document.getElementById("period_reading_table");
    if (!rt) {
      rt = document.getElementById("reading_table");
    }
    if (!rt) {
      // Table not found - might be in Date to Date mode, return silently
      return;
    }
    
    // Find tbody, or create it if it doesn't exist
    let tbody = rt.querySelector("tbody");
    if (!tbody) {
      tbody = document.createElement("tbody");
      rt.appendChild(tbody);
    }
    
    // Clear existing rows
    tbody.innerHTML = "";
    
    if(active===null) return;

    const p = periods[active];

    p.readings.forEach((r,i)=>{
      const sel = document.createElement("select");

      // Date selector: from period start (inclusive) to period end (exclusive)
      const currentDate = new Date(p.start);
      const endDate = new Date(p.end);
      // Use exposed iso function (from BillingEngineLogic container)
      const isoFunc = typeof iso !== 'undefined' ? iso : (d => d.toISOString().slice(0,10));
      while(currentDate < endDate){
        const o = document.createElement("option");
        o.value = isoFunc(currentDate);
        o.textContent = isoFunc(currentDate);
        sel.appendChild(o);
        currentDate.setDate(currentDate.getDate() + 1);
      }

      sel.value = r.date ?? "";
      sel.onchange = ()=> r.date = sel.value;

      const inp = document.createElement("input");
      inp.type="number";
      inp.value = r.value ?? "";
      inp.oninput = ()=> {
        const newValue = Number(inp.value);
        if(isNaN(newValue) || inp.value === "") {
          // Allow empty value for clearing
          r.value = null;
          return;
        }
        
        // NO VALIDATION HERE - Allow user to type freely
        // Validation will happen when Calculate button is clicked
        r.value = newValue;
      };

      const del = document.createElement("button");
      del.textContent="Delete";
      del.onclick=()=>{ 
        const readingInfo = r.date && r.value !== null ? `${r.date}: ${r.value} L` : 'Empty reading';
        p.readings.splice(i,1);
        if(typeof BillingEngineUI !== 'undefined' && BillingEngineUI.save_revision) {
          BillingEngineUI.save_revision('Reading Deleted', `Period ${active + 1}: ${readingInfo}`);
        } else if(typeof save_revision === 'function') {
          save_revision('Reading Deleted', `Period ${active + 1}: ${readingInfo}`);
        }
        render(); 
      };

      const tr = document.createElement("tr");
      
      // Date column
      const tdDate = document.createElement("td");
      tdDate.appendChild(sel);
      tr.appendChild(tdDate);
      
      // Reading column
      const tdReading = document.createElement("td");
      tdReading.appendChild(inp);
      tr.appendChild(tdReading);
      
      // ✅ REMOVED: Usage column calculation (VIOLATION - calculated outside calculator)
      // Usage must come from calculator output (period/sector view), not raw readings table
      // Usage is a DERIVED value calculated from consecutive readings, not an intrinsic property
      
      // Cost column (placeholder for now - will be calculated)
      const tdCost = document.createElement("td");
      tdCost.textContent = "—";
      tr.appendChild(tdCost);
      
      // Delete button column
      const tdDelete = document.createElement("td");
      tdDelete.appendChild(del);
      tr.appendChild(tdDelete);
      
      tbody.appendChild(tr);
    });
  } catch (error) {
    log_error(error.message);
    console.error("Error in render_readings:", error);
  }
}

// Canonical Day Function: Days_Between (inclusive-inclusive)
// Examples: Days_Between(20 Jan, 20 Jan) = 1, Days_Between(20 Jan, 30 Jan) = 11
function days_between(a, b){
  // Convert to Date objects if strings
  const dateA = a instanceof Date ? a : new Date(a);
  const dateB = b instanceof Date ? b : new Date(b);
  // Calculate difference in milliseconds, convert to days, add 1 for inclusive-inclusive
  return Math.floor((dateB - dateA) / 86400000) + 1;
}

/* ==================== CALCULATE ==================== */
function calculate(){
  try {
    // First, set up start_reading for Period 1
    periods.forEach((p, idx) => {
      const readings = p.readings
        .filter(r=>r.date&&r.value!==null)
        .map(r=>({d:new Date(r.date),v:r.value}))
        .sort((a,b)=>a.d-b.d);
      
      if(idx === 0){
        if(readings.length >= 1) p.start_reading = readings[0].v;
      }
    });
    
    // Create sectors from all readings
    const all_sectors = create_sectors_from_readings();
    
    // Recalculate any PROVISIONAL periods that have readings after their end date
    periods.forEach((p, period_idx) => {
      if(p.status === "CALCULATED" || p.status === "ACTUAL"){
        return; // Don't recalculate locked periods
      }
      
      const all_readings = get_all_readings_sorted();
      // Compare Date objects: p.end is exclusive (start of next period at 00:00:00)
      // A reading on or after p.end should trigger CALCULATED status
      const periodEndDate = new Date(p.end);
      periodEndDate.setHours(0, 0, 0, 0); // Normalize to start of day
      const reading_after_period_end = all_readings.find(r => {
        const readingDate = new Date(r.date);
        readingDate.setHours(12, 0, 0, 0); // Normalize reading date to noon for comparison
        return readingDate >= periodEndDate; // >= because p.end is exclusive
      });
      
      if(reading_after_period_end && (p.status === "PROVISIONAL" || p.status === "OPEN")){
        recalculate_period_from_sectors(period_idx);
      }
    });
    
    // Update opening for all periods using previous period's closing
    periods.forEach((p, idx) => {
      if(idx === 0){
        // Period 1 uses start_reading, already set
      } else {
        const prev = periods[idx - 1];
        p.opening = prev.closing;
      }
    });
    
    // Process each period for standard calculations
    periods.forEach((p, period_idx) => {
      const readings = p.readings
        .filter(r=>r.date&&r.value!==null)
        .map(r=>({d:new Date(r.date),v:r.value}))
        .sort((a,b)=>a.d-b.d);
      
      const end_display = new Date(p.end);
      end_display.setDate(end_display.getDate()-1);
      const period_days = days_between(p.start,end_display);
      
      // Check if reading exists exactly on period end date (ACTUAL)
      // Normalize dates to 12:00:00 for consistent comparison
      const end_display_normalized = new Date(end_display);
      end_display_normalized.setHours(12, 0, 0, 0);
      const reading_on_period_end = readings.find(r => {
        const readingDate = new Date(r.d);
        readingDate.setHours(12, 0, 0, 0);
        return readingDate.getTime() === end_display_normalized.getTime();
      });
      
      // Check if any reading exists after period end date
      const all_readings = get_all_readings_sorted();
      // Compare Date objects: p.end is exclusive (start of next period at 00:00:00)
      // A reading on or after p.end should trigger CALCULATED status
      const periodEndDate = new Date(p.end);
      periodEndDate.setHours(0, 0, 0, 0); // Normalize to start of day
      const reading_after_period_end = all_readings.find(r => {
        const readingDate = new Date(r.date);
        readingDate.setHours(12, 0, 0, 0); // Normalize reading date to noon for comparison
        return readingDate >= periodEndDate; // >= because p.end is exclusive
      });
      
      if(reading_on_period_end){
        // Reading exists exactly on period end - period is ACTUAL
        if(period_idx === 0){
          if(readings.length >= 2){
            const r_last = readings.at(-1);
            const days = days_between(readings[0].d, r_last.d);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.start_reading) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.start_reading + p.usage;
              p.status = "ACTUAL";
            }
          }
        } else {
          if(readings.length >= 1){
            const r_last = readings.at(-1);
            const co_reading_date = new Date(p.start);
            co_reading_date.setHours(12, 0, 0, 0);
            const last_reading_date = new Date(r_last.d);
            last_reading_date.setHours(12, 0, 0, 0);
            const days = days_between(co_reading_date, last_reading_date);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.opening) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.opening + p.usage;
              p.status = "ACTUAL";
            }
          }
        }
        p.sectors = get_sectors_for_period(period_idx);
      } else if(reading_after_period_end){
        // Reading exists after period end - use sector-based calculation
        if(p.status === "PROVISIONAL" || p.status === "OPEN"){
          recalculate_period_from_sectors(period_idx);
        } else {
          p.sectors = get_sectors_for_period(period_idx);
        }
      } else {
        // No reading after period end - use standard calculation
        if(period_idx === 0){
          if(readings.length >= 2){
            const r_last = readings.at(-1);
            const days = days_between(readings[0].d, r_last.d);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.start_reading) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.start_reading + p.usage;
              // Check against end_display (actual last day), not p.end (exclusive)
              const lastReadingDate = new Date(r_last.d);
              lastReadingDate.setHours(12, 0, 0, 0);
              const endDisplayNorm = new Date(end_display);
              endDisplayNorm.setHours(12, 0, 0, 0);
              p.status = lastReadingDate.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
              // Capture original PROVISIONAL value if first time calculating as PROVISIONAL
              if(p.status === "PROVISIONAL" && p.original_provisional_usage === null){
                p.original_provisional_usage = p.usage;
              }
            }
          } else if(readings.length === 1){
            const r1 = readings[0];
            const days = days_between(p.start, r1.d);
            if(days > 0){
              p.dailyUsage = (r1.v - p.start_reading) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.start_reading + p.usage;
              p.status = "PROVISIONAL";
              // Capture original PROVISIONAL value if first time calculating as PROVISIONAL
              if(p.original_provisional_usage === null){
                p.original_provisional_usage = p.usage;
              }
            }
          }
        } else {
          if(readings.length >= 1){
            const r_last = readings.at(-1);
            const co_reading_date = new Date(p.start);
            co_reading_date.setHours(12, 0, 0, 0);
            const last_reading_date = new Date(r_last.d);
            last_reading_date.setHours(12, 0, 0, 0);
            const days = days_between(co_reading_date, last_reading_date);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.opening) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.opening + p.usage;
              // Check against end_display (actual last day), not p.end (exclusive)
              const lastReadingDateNorm = new Date(r_last.d);
              lastReadingDateNorm.setHours(12, 0, 0, 0);
              const endDisplayNorm = new Date(end_display);
              endDisplayNorm.setHours(12, 0, 0, 0);
              p.status = lastReadingDateNorm.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
              // Capture original PROVISIONAL value if first time calculating as PROVISIONAL
              if(p.status === "PROVISIONAL" && p.original_provisional_usage === null){
                p.original_provisional_usage = p.usage;
              }
            }
          } else {
            // Period 2+ with no readings - use previous period's Daily_Usage if available
            const prev_period = periods[period_idx - 1];
            if (prev_period && prev_period.dailyUsage !== null && prev_period.dailyUsage !== undefined && prev_period.dailyUsage > 0) {
              p.dailyUsage = prev_period.dailyUsage;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.opening + p.usage;
              p.status = "PROVISIONAL";
              // Capture original PROVISIONAL value if first time calculating as PROVISIONAL
              if(p.original_provisional_usage === null){
                p.original_provisional_usage = p.usage;
              }
            }
          }
        }
        p.sectors = get_sectors_for_period(period_idx);
      }
    });
    
    // Track revision
    save_revision('Calculation Performed', `All periods recalculated`);
    
    render();
    if(active !== null){
      render_calculation_output();
    }
  } catch (error) {
    log_error(error.message);
    console.error("Error in calculate:", error);
  }
}


/* ==================== SECTOR SYSTEM ==================== */

// Collect all readings across all periods, sorted chronologically
function get_all_readings_sorted(){
  const all_readings = [];
  periods.forEach((p, period_idx) => {
    p.readings
      .filter(r => r.date && r.value !== null)
      .forEach(r => {
        all_readings.push({
          date: new Date(r.date),
          value: r.value,
          period_index: period_idx
        });
      });
  });
  all_readings.sort((a, b) => a.date - b.date);
  return all_readings;
}

// Create sectors from consecutive reading pairs using calendar day ranges
// Each sector uses a calendar day range to avoid overlap
function create_sectors_from_readings(){
  const all_readings = get_all_readings_sorted();
  if(all_readings.length < 2) return [];
  
  const sectors = [];
  let sector_id = 1;
  
  // Create sectors from consecutive reading pairs
  // Each sector uses calendar day ranges to avoid overlap
  for(let i = 0; i < all_readings.length - 1; i++){
    const earlier = all_readings[i];
    const later = all_readings[i + 1];
    
    // Sector start: earlier reading date (inclusive)
    // Sector end: later reading date (inclusive)
    // But for day calculation, we need to split to avoid overlap
    
    // If this is the first sector, it starts on the reading date
    // If this is a subsequent sector, it starts the day AFTER the previous sector's end
    let sector_start_date;
    let sector_start_reading;
    
    if(i === 0){
      // First sector: starts on first reading date
      sector_start_date = new Date(earlier.date);
      sector_start_reading = earlier.value;
    } else {
      // Subsequent sectors: start the day AFTER the previous reading date
      // Use the previous reading as the baseline
      sector_start_date = new Date(earlier.date);
      sector_start_date.setDate(sector_start_date.getDate() + 1);
      sector_start_reading = earlier.value; // Use previous reading as baseline
    }
    
    // Sector end: later reading date (inclusive)
    const sector_end_date = new Date(later.date);
    
    // Calculate days: from sector_start_date to sector_end_date (both inclusive)
    const sector_days = days_between(sector_start_date, sector_end_date);
    
    // Sector usage: difference between end reading and start reading
    const sector_usage = later.value - sector_start_reading;
    
    // Create sector
    const sector = {
      sector_id: sector_id++,
      start_date: sector_start_date,
      end_date: sector_end_date,
      start_reading: sector_start_reading,
      end_reading: later.value,
      sector_usage: sector_usage,
      sector_days: sector_days,
      daily_usage: 0,
      sub_sectors: [],
      crosses_period: false
    };
    
    if(sector.sector_days > 0){
      sector.daily_usage = sector.sector_usage / sector.sector_days;
    }
    sectors.push(sector);
  }
  
  // Check each sector for period boundary crossings and split if needed
  sectors.forEach(sector => {
    split_sector_at_period_boundaries(sector);
  });
  
  return sectors;
}

// Split sector at period boundaries if it crosses them
function split_sector_at_period_boundaries(sector){
  // Normalize sector dates for comparison
  const sector_start_normalized = new Date(sector.start_date);
  sector_start_normalized.setHours(12, 0, 0, 0);
  const sector_end_normalized = new Date(sector.end_date);
  sector_end_normalized.setHours(12, 0, 0, 0);
  
  // Find all period boundaries that this sector crosses
  const boundaries = [];
  periods.forEach((p, idx) => {
    const period_end_normalized = new Date(p.end);
    period_end_normalized.setHours(12, 0, 0, 0);
    
    // Sector crosses boundary if it starts before period end and ends on or after period end
    if(sector_start_normalized.getTime() < period_end_normalized.getTime() && 
       sector_end_normalized.getTime() >= period_end_normalized.getTime()){
      boundaries.push({period_index: idx, end_date: p.end});
    }
  });
  
  if(boundaries.length === 0){
    // Sector doesn't cross any boundaries
    sector.crosses_period = false;
    return;
  }
  
  // Sort boundaries by date
  boundaries.sort((a, b) => a.end_date - b.end_date);
  
  sector.crosses_period = true;
  let current_start = new Date(sector.start_date);
  let sub_id_letter = 'a';
  
  boundaries.forEach((boundary, idx) => {
    const period_end = new Date(boundary.end_date);
    period_end.setHours(12, 0, 0, 0);
    const current_start_normalized = new Date(current_start);
    current_start_normalized.setHours(12, 0, 0, 0);
    
    // Create sub-sector for period before boundary
    // period_end is exclusive, so the last day of the period is period_end - 1 day
    const period_end_inclusive = new Date(period_end);
    period_end_inclusive.setDate(period_end_inclusive.getDate() - 1);
    const days_before = days_between(current_start_normalized, period_end_inclusive);
    const usage_before = sector.daily_usage * days_before;
    
    // Ensure sub_id is always a valid string
    const sub_id = `${sector.sector_id}${sub_id_letter}`;
    sub_id_letter = String.fromCharCode(sub_id_letter.charCodeAt(0) + 1);
    
    sector.sub_sectors.push({
      sub_id: sub_id,
      period_index: boundary.period_index,
      start_date: new Date(current_start),
      end_date: new Date(period_end),
      days_in_period: days_before,
      usage_in_period: usage_before
    });
    
    current_start = period_end;
  });
  
  // Create final sub-sector for remaining period(s)
  if(current_start < sector.end_date){
    // Find which period the end date falls in
    let end_period_idx = -1;
    for(let i = 0; i < periods.length; i++){
      if(sector.end_date >= periods[i].start && sector.end_date < periods[i].end){
        end_period_idx = i;
        break;
      }
    }
    
    if(end_period_idx >= 0){
      // Normalize dates for calculation
      const current_start_normalized = new Date(current_start);
      current_start_normalized.setHours(12, 0, 0, 0);
      const sector_end_normalized = new Date(sector.end_date);
      sector_end_normalized.setHours(12, 0, 0, 0);
      
      const days_after = days_between(current_start_normalized, sector_end_normalized);
      const usage_after = sector.daily_usage * days_after;
      
      // Ensure sub_id is always a valid string
      const sub_id = `${sector.sector_id}${sub_id_letter}`;
      
      sector.sub_sectors.push({
        sub_id: sub_id,
        period_index: end_period_idx,
        start_date: new Date(current_start),
        end_date: new Date(sector.end_date),
        days_in_period: days_after,
        usage_in_period: usage_after
      });
    }
  }
}

// Get all sectors and sub-sectors contributing to a specific period (with full details)
function get_sectors_for_period(period_index){
  if(period_index === null || period_index < 0 || period_index >= periods.length) return [];
  
  const all_sectors = create_sectors_from_readings();
  const period_sectors = [];
  const p = periods[period_index];
  
  // Normalize period dates to noon for comparison
  const period_start = new Date(p.start);
  period_start.setHours(12, 0, 0, 0);
  const period_end = new Date(p.end);
  period_end.setHours(12, 0, 0, 0);
  
  all_sectors.forEach(sector => {
    // Normalize sector dates to noon for comparison
    const sector_start = new Date(sector.start_date);
    sector_start.setHours(12, 0, 0, 0);
    const sector_end = new Date(sector.end_date);
    sector_end.setHours(12, 0, 0, 0);
    
    if(!sector.crosses_period){
      // Sector doesn't cross boundaries - check if it's within this period
      // Sector is in period if it starts on or after period start and ends before period end
      if(sector_start.getTime() >= period_start.getTime() && 
         sector_end.getTime() < period_end.getTime()){
        period_sectors.push({
          sector_id: sector.sector_id,
          sub_id: null,
          start_date: new Date(sector.start_date),
          end_date: new Date(sector.end_date),
          start_reading: sector.start_reading,
          end_reading: sector.end_reading,
          total_usage: sector.sector_usage,
          days_in_period: sector.sector_days,
          usage_in_period: sector.sector_usage,
          daily_usage: sector.daily_usage
        });
      }
    } else {
      // Sector crosses boundaries - get sub-sectors for this period
      sector.sub_sectors.forEach(sub => {
        if(sub.period_index === period_index){
          // Calculate start_reading and end_reading for sub-sector
          // Find the actual reading date that corresponds to sector.start_reading
          const all_readings = get_all_readings_sorted();
          const reading_before_sector = all_readings.find(r => r.value === sector.start_reading);
          
          if(reading_before_sector){
            const sub_start_normalized = new Date(sub.start_date);
            sub_start_normalized.setHours(12, 0, 0, 0);
            
            let start_reading;
            let end_reading;
            
            // Check if sub starts on a period boundary (new period start)
            const period_start_match = periods.find((period, idx) => {
              const period_start_check = new Date(period.start);
              period_start_check.setHours(12, 0, 0, 0);
              return period_start_check.getTime() === sub_start_normalized.getTime() && idx > 0;
            });
            
            if(period_start_match){
              // Sub-sector starts a new period - use previous period's closing
              const period_idx = periods.indexOf(period_start_match);
              const prev_period = periods[period_idx - 1];
              const prev_closing = (prev_period.calculated_closing !== null && prev_period.calculated_closing !== undefined)
                ? prev_period.calculated_closing
                : prev_period.closing;
              start_reading = prev_closing || 0;
              end_reading = start_reading + sub.usage_in_period;
            } else {
              // Sub-sector is within the same period - interpolate from reading date
              const reading_date = new Date(reading_before_sector.date);
              reading_date.setHours(12, 0, 0, 0);
              
              let days_from_reading;
              if(sub_start_normalized.getTime() === reading_date.getTime()){
                days_from_reading = 0;
              } else if(sub_start_normalized.getTime() > reading_date.getTime()){
                const days_calc = days_between(reading_date, sub_start_normalized);
                days_from_reading = (days_calc === 2) ? 0 : (days_calc - 1);
              } else {
                days_from_reading = 0;
              }
              
              start_reading = sector.start_reading + (sector.daily_usage * days_from_reading);
              end_reading = start_reading + sub.usage_in_period;
            }
            
            period_sectors.push({
              sector_id: sector.sector_id,
              sub_id: sub.sub_id,
              start_date: sub.start_date,
              end_date: sub.end_date,
              start_reading: start_reading,
              end_reading: end_reading,
              total_usage: sub.usage_in_period,
              days_in_period: sub.days_in_period,
              usage_in_period: sub.usage_in_period,
              daily_usage: sector.daily_usage
            });
          } else {
            // Fallback: use sector.start_date as baseline
            const days_from_sector_start = days_between(sector.start_date, sub.start_date);
            const start_reading = sector.start_reading + (sector.daily_usage * days_from_sector_start);
            const end_reading = start_reading + sub.usage_in_period;
            
            period_sectors.push({
              sector_id: sector.sector_id,
              sub_id: sub.sub_id,
              start_date: sub.start_date,
              end_date: sub.end_date,
              start_reading: start_reading,
              end_reading: end_reading,
              total_usage: sub.usage_in_period,
              days_in_period: sub.days_in_period,
              usage_in_period: sub.usage_in_period,
              daily_usage: sector.daily_usage
            });
          }
        }
      });
    }
  });
  
  return period_sectors;
}

/* ==================== RECALCULATE PERIOD FROM SECTORS ==================== */
/**
 * Recalculate a period's usage and closing reading from sectors when late readings arrive
 * This is called when a reading exists after the period end date
 * @param {number} period_index - Index of the period to recalculate
 */
function recalculate_period_from_sectors(period_index){
  const p = periods[period_index];
  const period_sectors = get_sectors_for_period(period_index);
  
  if(period_sectors.length === 0) return;
  
  // Sum usage from all sectors/sub-sectors
  let total_usage = 0;
  let total_days = 0;
  
  period_sectors.forEach(s => {
    total_usage += (s.usage_in_period ?? s.total_usage ?? s.sector_usage ?? 0);
    total_days += (s.days_in_period ?? s.sector_days ?? 0);
  });
  
  const end_display = new Date(p.end);
  end_display.setDate(end_display.getDate() - 1);
  const period_days = days_between(p.start, end_display);
  
  // Preserve original PROVISIONAL usage before recalculating
  // This happens when a late reading arrives and the period is recalculated from sectors
  if(p.original_provisional_usage === null && p.status === "PROVISIONAL" && p.usage !== null && p.usage !== undefined){
    p.original_provisional_usage = p.usage;
  }
  
  // DEFENSIVE CHECK: Prevent negative usage
  if(total_usage < 0) {
    throw new Error(`INVALID: Period usage cannot be negative (${total_usage} L). One or more readings are invalid. Please correct the reading values.`);
  }
  
  // Update period with sector-based calculation
  p.usage = total_usage;
  p.dailyUsage = total_usage / period_days;
  
  // Update closing reading
  if(period_index === 0){
    // Period 1: use start_reading
    p.closing = (p.start_reading || 0) + total_usage;
  } else {
    // Period 2+: use previous period's closing
    const prev = periods[period_index - 1];
    p.opening = prev.closing;
    p.closing = (p.opening || 0) + total_usage;
  }
  
  // DEFENSIVE CHECK: Ensure closing reading is not less than opening
  if(period_index > 0 && p.opening !== null && p.closing !== null && p.closing < p.opening) {
    throw new Error(`INVALID: Period closing reading (${p.closing} L) cannot be less than opening reading (${p.opening} L). Usage is negative. Please correct the reading values.`);
  }
  
  // Update status to CALCULATED (reading exists after period end)
  p.status = "CALCULATED";
  
  // Store sectors for this period
  p.sectors = period_sectors;
}

/* ==================== UI MODULE: RENDER CALCULATION OUTPUT ==================== */
function render_calculation_output(){
  try {
    // Check for both container IDs (legacy and new)
    let outputContainer = document.getElementById("period_output_container");
    if (!outputContainer) {
      outputContainer = document.getElementById("output_container");
    }
    if (!outputContainer) {
      // Silently return if container not found (might be in Date to Date mode)
      return;
    }
    
    if(active===null){ 
      outputContainer.innerHTML = '<div class="output-section"><div class="output-header">No Period Selected</div></div>'; 
      return; 
    }
    
    const p = periods[active];
    // Period end is exclusive, so calculate days from start to (end - 1 day) for actual period span
    const period_end_display = new Date(p.end);
    period_end_display.setDate(period_end_display.getDate() - 1);
    const period_days = days_between(p.start, period_end_display);
    
    // Always refresh sectors for this period (they may have changed if readings were added/removed)
    p.sectors = get_sectors_for_period(active);
    
    let html = '';
    
    const validReadings = p.readings.filter(r => r.date && r.value !== null).sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Meter Readings Section
    if (validReadings.length > 0) {
      html += `<div class="output-section">
        <div class="output-header readings" onclick="this.parentElement.classList.toggle('collapsed')">Meter Readings</div>
        <div class="output-content">
          <div class="output-grid">`;
      
      validReadings.forEach((r, i) => {
        html += `<div class="output-field">
          <div class="output-label">Reading ${i + 1}</div>
          <div class="output-value calculable-field" data-field="readings" title="Right-click for calculation details">${r.date} → ${format_number(r.value)} L</div>
        </div>`;
      });
      
      html += `</div></div></div>`;
    }
    
    // Period Header with Status
    const status = p.status || 'PROVISIONAL';
    const statusColor = status === 'ACTUAL' ? '#10b981' : status === 'CALCULATED' ? '#3b82f6' : status === 'PROVISIONAL' ? '#f59e0b' : status === 'UNRESOLVED' ? '#ef4444' : '#6b7280';
    const isClosed = p.is_closed !== undefined ? p.is_closed : false;
    const closedLabel = isClosed ? 'CLOSED' : 'OPEN';
    
    html += `<div class="output-section">
      <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <div style="font-size:18px; font-weight:700; color:var(--text);">Period ${active + 1}</div>
          <div style="font-size:12px; color:var(--muted); margin-top:2px;">${format_period_display(p)}</div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="padding:4px 12px; background:${statusColor}20; color:${statusColor}; border-radius:4px; font-size:12px; font-weight:600;">${status}</span>
          <span style="padding:4px 12px; background:${isClosed ? '#10b98120' : '#f59e0b20'}; color:${isClosed ? '#10b981' : '#f59e0b'}; border-radius:4px; font-size:12px; font-weight:600;">${closedLabel}</span>
        </div>
      </div>
      <div class="output-content">`;
    
    // Period Opening State Section (Period 1 or Period 2+)
    if (active === 0 && p.start_reading !== null && p.start_reading !== undefined) {
      html += `<div class="output-grid">
        <div class="output-field">
          <div class="output-label">Start Reading</div>
          <div class="output-value">${format_number(p.start_reading)} L</div>
        </div>
      </div>`;
    } else if (active > 0 && p.opening !== null && p.opening !== undefined) {
      html += `<div class="output-grid">
        <div class="output-field">
          <div class="output-label">Opening Reading</div>
          <div class="output-value">${format_number(p.opening)} L</div>
        </div>
      </div>`;
      
      html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">(Carried forward from Period ${active}'s Closing_Reading)</div>`;
    }
    
    html += `</div></div>`;
    
    // CO Reading (Closing Opening) Section
    if (p.closing !== null && p.closing !== undefined) {
      html += `<div class="output-section">
        <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')">CO Reading (Closing Opening)</div>
        <div class="output-content">`;
      
      // Show closing reading (with original PROVISIONAL usage if period was recalculated)
      html += `<div class="output-grid">
        <div class="output-field">
          <div class="output-label">CLOSING_READING</div>
          <div class="output-value">${format_number(p.closing)} L</div>
        </div>
      </div>`;
      if(p.status === "CALCULATED" && p.original_provisional_usage !== null && p.original_provisional_usage !== undefined){
        // Show that this was recalculated from PROVISIONAL
        const original_closing = active === 0 
          ? (p.start_reading || 0) + p.original_provisional_usage
          : (p.opening || 0) + p.original_provisional_usage;
        html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">(Recalculated from sectors after late reading. Original PROVISIONAL: ${format_number(original_closing)} L)</div>`;
      } else {
        html += `<div style="font-size: 11px; color: var(--muted); margin-top: -8px; margin-bottom: 10px;">${active === 0 ? '(Calculated from START_READING + Period_Total_Usage)' : '(Calculated from OPENING_READING + Period_Total_Usage)'}</div>`;
      }
      
      html += `</div></div>`;
    }
    
    // Usage Calculation Section
    if (p.usage !== null && p.usage !== undefined) {
      const daily_usage = p.dailyUsage || (p.usage / period_days);
      
      html += `<div class="output-section">
        <div class="output-header usage" onclick="this.parentElement.classList.toggle('collapsed')">Usage Calculation</div>
        <div class="output-content">
          <div class="output-grid">
            <div class="output-field">
              <div class="output-label">Total Period Usage</div>
              <div class="output-value calculable-field" data-field="period_usage" title="Right-click for calculation details">${format_number(p.usage)} L</div>
            </div>
            <div class="output-field">
              <div class="output-label">Daily Usage</div>
              <div class="output-value calculable-field" data-field="daily_usage" title="Right-click for calculation details">${format_number(daily_usage)} L/day</div>
            </div>
            <div class="output-field">
              <div class="output-label">Closing Reading</div>
              <div class="output-value calculable-field" data-field="closing_reading" title="Right-click for calculation details">${format_number(p.closing)} L</div>
            </div>
            <div class="output-field">
              <div class="output-label">Period Days</div>
              <div class="output-value calculable-field" data-field="period_days" title="Right-click for calculation details">${period_days}</div>
            </div>
          </div>
        </div>
      </div>`;
      
      // Sector Breakdown Section (REQUIRED per Output Module Specification)
      if(p.sectors && p.sectors.length > 0){
        html += `<div class="output-section">
          <div class="output-header sector" onclick="this.parentElement.classList.toggle('collapsed')">Sector Breakdown</div>
          <div class="output-content">`;
        
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
          
          // Sector label displayed OUTSIDE the card, before the details (like stable version)
          html += `<div class="sector-item">
            <div class="sector-label">${sector_label}</div>
            <div class="output-grid">`;
          html += `<div class="output-field"><div class="output-label">Start Date</div><div class="output-value">${iso(s.start_date)}</div></div>`;
          html += `<div class="output-field"><div class="output-label">End Date</div><div class="output-value">${iso(s.end_date)}</div></div>`;
          html += `<div class="output-field"><div class="output-label">Start Reading</div><div class="output-value">${(s.start_reading ?? 0).toFixed(0)} L</div></div>`;
          html += `<div class="output-field"><div class="output-label">End Reading</div><div class="output-value">${(s.end_reading ?? 0).toFixed(0)} L</div></div>`;
          html += `<div class="output-field"><div class="output-label">Total Usage</div><div class="output-value">${total_usage.toFixed(0)} L</div></div>`;
          html += `<div class="output-field"><div class="output-label">Daily Usage</div><div class="output-value">${daily_usage.toFixed(2)} L/day</div></div>`;
          html += `<div class="output-field"><div class="output-label">Days in Period</div><div class="output-value">${days_in_period} days</div></div>`;
          html += `</div></div>`;
        });
        
        // Validation
        let total_sector_days = 0;
        let total_sector_usage = 0;
        p.sectors.forEach(s => {
          total_sector_days += (s.days_in_period ?? s.sector_days ?? 0);
          total_sector_usage += (s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0);
        });
        
        const weighted_sector_daily_usage = total_sector_days > 0 ? total_sector_usage / total_sector_days : 0;
        const period_daily_usage = p.dailyUsage || (p.usage / period_days);
        const daily_usage_match = Math.abs(weighted_sector_daily_usage - period_daily_usage) < 0.01;
        
        let validation_note = "";
        if(p.status === "ACTUAL"){
          validation_note = " (Sectors validate ACTUAL period - calculation not overridden)";
        } else if(p.status === "CALCULATED"){
          validation_note = " (Sectors used for recalculation from PROVISIONAL to CALCULATED)";
        } else {
          validation_note = " (Sectors validate PROVISIONAL period - only daily_usage is validated)";
        }
        
        if(daily_usage_match){
          if(p.status === "PROVISIONAL"){
            html += `<div class="validation-success">✓ Validation Passed: Daily Usage matches (${weighted_sector_daily_usage.toFixed(2)} L/day = ${period_daily_usage.toFixed(2)} L/day). Sector days (${total_sector_days}) and usage (${total_sector_usage.toFixed(0)} L) are actual values, period values are projected${validation_note}</div>`;
          } else {
            html += `<div class="validation-success">✓ Validation Passed: Sum of sector days (${total_sector_days}) = Period Days (${period_days}), Sum of sector usage (${total_sector_usage.toFixed(0)} L) = Period Total Usage (${p.usage.toFixed(0)} L)${validation_note}</div>`;
          }
        } else {
          const failures = [];
          if(p.status !== "PROVISIONAL"){
            if(total_sector_days !== period_days) failures.push(`Days match: false (${total_sector_days} vs ${period_days})`);
            if(Math.abs(total_sector_usage - p.usage) > 0.01) failures.push(`Usage match: false (${total_sector_usage.toFixed(0)} vs ${p.usage.toFixed(0)})`);
          }
          if(!daily_usage_match) failures.push(`Daily Usage match: false (${weighted_sector_daily_usage.toFixed(2)} vs ${period_daily_usage.toFixed(2)})`);
          
          html += `<div class="validation-error">⚠ Validation Failed: ${failures.join(", ")}${validation_note}</div>`;
        }
        
        html += `</div></div>`;
      }
      
      // Calculate tier charges
      const tiers = get_tiers();
      let remaining = p.usage;
      let prev = 0;
      let total = 0;
      const tier_items = [];
      
      for(const t of tiers){
        const cap = t.max - prev;
        const used = Math.max(0, Math.min(remaining, cap));
        if(used > 0){
          const cost = (used / 1000) * t.rate;
          tier_items.push({prev, max: t.max, used, rate: t.rate, cost});
          total += cost;
          remaining -= used;
        }
        prev = t.max;
      }
      
      const daily_cost = total / period_days;
      
      // Tier Charges Section (Collapsible)
      if (tier_items.length > 0) {
        html += `<div class="output-section collapsed">
          <div class="output-header tier" onclick="this.parentElement.classList.toggle('collapsed')">
            Tier Charges
          </div>
          <div class="output-content">
            <div class="output-grid">`;
        
        tier_items.forEach(item => {
          html += `<div class="output-field">
            <div class="output-label">Tier ${item.prev}–${item.max} L</div>
            <div class="output-value">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>
          </div>`;
        });
        
        html += `</div></div></div>`;
      }
      
      // Reconciliation Tier Cost Section - Show ALL consecutive recalculated periods
      // Only shown in the period where the reading was done (immediately after recalculated periods)
      if(active > 0){
        // Find consecutive recalculated periods ending just before the current period
        let consecutive_recalculated = [];
        for(let i = active - 1; i >= 0; i--){
          const check_period = periods[i];
          if(check_period.status === "CALCULATED" && 
             check_period.original_provisional_usage !== null && 
             check_period.original_provisional_usage !== undefined &&
             check_period.usage !== null && 
             check_period.usage !== undefined){
            consecutive_recalculated.unshift(i); // Add to front to maintain order
          } else {
            // Stop if we hit a non-recalculated period (not consecutive)
            break;
          }
        }
        
        // Only show reconciliation in the period immediately after the consecutive recalculated periods
        // AND only if the current period is NOT CALCULATED (it's PROVISIONAL or ACTUAL)
        if(consecutive_recalculated.length > 0 && 
           active === consecutive_recalculated[consecutive_recalculated.length - 1] + 1 &&
           p.status !== "CALCULATED"){
          consecutive_recalculated.forEach(period_idx => {
            const check_period = periods[period_idx];
            
            // Calculate usage amounts
            const provisioned_usage = check_period.original_provisional_usage;
            const calculated_usage = check_period.usage;
            const adjustment_litres = calculated_usage - provisioned_usage;
            
            const reconciliation_cost = calculate_reconciliation_tier_cost(
              adjustment_litres, 
              provisioned_usage, 
              calculated_usage
            );
            
            if(reconciliation_cost !== null){
              const period_num = period_idx + 1;
              html += `<div class="output-section">
                <div class="output-header reconciliation" onclick="this.parentElement.classList.toggle('collapsed')">Reconciliation (Period ${period_num})</div>
                <div class="output-content">
                  <div style="font-size: 12px; color: var(--muted); margin-bottom: 12px;">
                    Reconciliation for Period ${period_num} displayed in Period ${active + 1}
                  </div>
                  
                  <div class="output-grid" style="margin-bottom: 12px;">
                    <div class="output-field">
                      <div class="output-label">Reconciliation Amount</div>
                      <div class="output-value" style="color: ${adjustment_litres >= 0 ? '#dc3545' : '#28a745'};">
                        ${adjustment_litres >= 0 ? '+' : ''}${format_number(adjustment_litres)} L
                      </div>
                    </div>
                    <div class="output-field">
                      <div class="output-label">Reconciliation Cost</div>
                      <div class="output-value" style="color: ${reconciliation_cost.total_cost >= 0 ? '#dc3545' : '#28a745'};">
                        ${reconciliation_cost.total_cost >= 0 ? '+' : ''}R ${Math.abs(reconciliation_cost.total_cost).toFixed(2)}
                      </div>
                    </div>
                  </div>
                  
                  <div style="font-size: 11px; color: var(--muted); margin-bottom: 8px;">
                    (CALCULATED Usage ${reconciliation_cost.calculated_litres.toFixed(0)} L - PROVISIONED Usage ${reconciliation_cost.provisioned_litres.toFixed(0)} L)
                  </div>
                  
                  <div style="margin-bottom: 16px; padding: 12px; background: rgba(255, 193, 7, 0.1); border-left: 3px solid #ffc107; border-radius: 4px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: #856404;">1️⃣ PROVISIONED Usage (${reconciliation_cost.provisioned_litres.toFixed(0)} L) - Original Bill (To Credit):</div>
                    ${reconciliation_cost.provisioned_breakdown.length > 0 ? `
                      <div class="output-grid" style="margin-top: 8px;">
                        ${reconciliation_cost.provisioned_breakdown.map(item => `
                          <div class="output-field">
                            <div class="output-label">Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L</div>
                            <div class="output-value">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>
                          </div>
                        `).join('')}
                      </div>
                    ` : '<div style="color: var(--muted);">No tier allocation (0 L)</div>'}
                    <div style="margin-top: 8px; font-weight: 600;">Total Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}</div>
                  </div>
                  
                  <div style="margin-bottom: 16px; padding: 12px; background: rgba(40, 167, 69, 0.1); border-left: 3px solid #28a745; border-radius: 4px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: #155724;">2️⃣ CALCULATED Usage (${reconciliation_cost.calculated_litres.toFixed(0)} L) - Corrected Bill (To Charge):</div>
                    ${reconciliation_cost.calculated_breakdown.length > 0 ? `
                      <div class="output-grid" style="margin-top: 8px;">
                        ${reconciliation_cost.calculated_breakdown.map(item => `
                          <div class="output-field">
                            <div class="output-label">Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L</div>
                            <div class="output-value">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>
                          </div>
                        `).join('')}
                      </div>
                    ` : '<div style="color: var(--muted);">No tier allocation (0 L)</div>'}
                    <div style="margin-top: 8px; font-weight: 600;">Total Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}</div>
                  </div>
                  
                  <div style="padding: 12px; background: rgba(0, 123, 255, 0.1); border-left: 3px solid #007bff; border-radius: 4px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: #004085;">3️⃣ Reconciliation Cost Calculation:</div>
                    <div style="font-size: 13px; line-height: 1.6;">
                      <div>Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}</div>
                      <div>Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}</div>
                      <div style="margin-top: 4px; font-weight: 600;">
                        Reconciliation Cost = R${reconciliation_cost.calculated_cost.toFixed(2)} - R${reconciliation_cost.provisioned_cost.toFixed(2)} = 
                        <span style="color: ${reconciliation_cost.total_cost >= 0 ? '#dc3545' : '#28a745'};">
                          ${reconciliation_cost.total_cost >= 0 ? 'R' : '-R'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>`;
            }
          });
        }
      }
      
      // Cost Summary Section
      html += `<div class="output-section">
        <div class="output-header cost" onclick="this.parentElement.classList.toggle('collapsed')">Cost Summary</div>
        <div class="output-content">
          <div class="output-grid">
            <div class="output-field">
              <div class="output-label">Total Cost</div>
              <div class="output-value total">R ${total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")}</div>
            </div>
            <div class="output-field">
              <div class="output-label">Average Daily Cost</div>
              <div class="output-value">R ${daily_cost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")} / day</div>
            </div>
          </div>
        </div>
      </div>`;
    } else {
      html += `<div class="output-section">
        <div class="output-header usage" onclick="this.parentElement.classList.toggle('collapsed')">Usage Calculation</div>
        <div class="output-content">
          <div class="validation-warning">Not calculated (need at least 2 readings)</div>
        </div>
      </div>`;
    }
    
    outputContainer.innerHTML = html;
    
    // Initialize tier sections as expanded by default
    const tierId = `tier-section-${active}`;
    const tierContent = document.getElementById(tierId);
    if (tierContent) {
      tierContent.style.maxHeight = tierContent.scrollHeight + 'px';
    }
  } catch (error) {
    log_error(error.message);
    console.error("Error in render_calculation_output:", error);
  }
}

// toggleTierSection and toggleTariffSection functions removed - using inline onclick handlers now

// Initialize tariff section as expanded on page load
document.addEventListener('DOMContentLoaded', function() {
  const tariffContent = document.getElementById('tariff-content');
  if (tariffContent) {
    tariffContent.style.maxHeight = tariffContent.scrollHeight + 'px';
    tariffContent.style.opacity = '1';
  }
});

/* ==================== STEP-BY-STEP DEBUGGING FUNCTIONS ==================== */
function show_sector_analysis(){
  try {
    const debugOutput = document.getElementById("debug_output");
    if (!debugOutput) {
      log_error("debug_output element not found");
      return;
    }
    
    let html = '<h3 style="color: #17a2b8; margin-top: 0;">📊 Sector Analysis</h3>';
    
    // Get all readings sorted
    const all_readings = get_all_readings_sorted();
    
    html += '<h4 style="color: #495057; margin-top: 15px;">📅 All Readings:</h4>';
    if(all_readings.length === 0){
      html += '<div style="color: #dc3545; padding: 10px;">No readings found</div>';
    } else {
      html += '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
      html += '<tr style="background: #e9ecef;"><th style="padding: 8px; border: 1px solid #dee2e6;">#</th><th style="padding: 8px; border: 1px solid #dee2e6;">Date</th><th style="padding: 8px; border: 1px solid #dee2e6;">Reading (L)</th><th style="padding: 8px; border: 1px solid #dee2e6;">Days Between</th></tr>';
      all_readings.forEach((r, idx) => {
        const days_between_text = idx > 0 
          ? `${days_between(all_readings[idx - 1].date, r.date)} days`
          : '—';
        html += `<tr style="background: ${idx % 2 === 0 ? '#fff' : '#f8f9fa'};">
          <td style="padding: 8px; border: 1px solid #dee2e6;">${idx + 1}</td>
          <td style="padding: 8px; border: 1px solid #dee2e6;">${r.date}</td>
          <td style="padding: 8px; border: 1px solid #dee2e6;">${format_number(r.value)} L</td>
          <td style="padding: 8px; border: 1px solid #dee2e6;">${days_between_text}</td>
        </tr>`;
      });
      html += '</table>';
    }
    
    // Get all sectors
    const all_sectors = create_sectors_from_readings();
    
    html += '<h4 style="color: #495057; margin-top: 20px;">🔷 All Sectors and Sub-Sectors:</h4>';
    if(all_sectors.length === 0){
      html += '<div style="color: #dc3545; padding: 10px;">No sectors found</div>';
    } else {
      all_sectors.forEach((sector, sector_idx) => {
        const sector_label = sector.sub_sectors && sector.sub_sectors.length > 0 
          ? `Sector ${sector.sector_id} (split into ${sector.sub_sectors.length} sub-sectors)`
          : `Sector ${sector.sector_id}`;
        
        html += `<div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #17a2b8; border-radius: 4px;">`;
        html += `<div style="font-weight: bold; color: #004085; margin-bottom: 8px;">${sector_label}</div>`;
        html += `<div style="font-size: 11px; color: #6c757d; margin-bottom: 8px;">Start: ${sector.start_date} | End: ${sector.end_date} | Daily Usage: ${sector.daily_usage.toFixed(2)} L/day</div>`;
        
        if(sector.sub_sectors && sector.sub_sectors.length > 0){
          html += '<table style="width: 100%; border-collapse: collapse; margin-top: 8px;">';
          html += '<tr style="background: #cfe2ff;"><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Sub-Sector</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Period</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Days</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Usage (L)</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Daily Usage</th></tr>';
          sector.sub_sectors.forEach(sub => {
            html += `<tr style="background: #fff;">
              <td style="padding: 6px; border: 1px solid #b6d4fe; font-weight: bold; color: #004085;">${sub.sub_id}</td>
              <td style="padding: 6px; border: 1px solid #b6d4fe;">Period ${sub.period_index + 1}</td>
              <td style="padding: 6px; border: 1px solid #b6d4fe;">${sub.days_in_period} days</td>
              <td style="padding: 6px; border: 1px solid #b6d4fe;">${format_number(sub.usage_in_period)} L</td>
              <td style="padding: 6px; border: 1px solid #b6d4fe;">${sector.daily_usage.toFixed(2)} L/day</td>
            </tr>`;
          });
          html += '</table>';
        } else {
          html += `<div style="font-size: 11px; color: #6c757d;">Single sector (no sub-sectors) - ${sector.sector_days || 0} days, ${format_number(sector.total_usage || 0)} L</div>`;
        }
        html += '</div>';
      });
    }
    
    debugOutput.innerHTML = html;
  } catch (error) {
    log_error(error.message);
    console.error("Error in show_sector_analysis:", error);
  }
}

function next_period_update(){
  try {
    const debugOutput = document.getElementById("debug_output");
    if (!debugOutput) {
      log_error("debug_output element not found");
      return;
    }
    
    // Find earliest PROVISIONAL period
    let target_period_idx = null;
    for(let i = 0; i < periods.length; i++){
      if(periods[i].status === "PROVISIONAL"){
        target_period_idx = i;
        break;
      }
    }
    
    if(target_period_idx === null){
      debugOutput.innerHTML = '<div style="color: #856404; background: #fff3cd; padding: 15px; border-radius: 4px; border: 1px solid #ffc107;">✅ No PROVISIONAL periods found. All periods are calculated.</div>';
      return;
    }
    
    const p = periods[target_period_idx];
    
    // Store BEFORE state
    const before_state = {
      opening: p.opening,
      usage: p.usage,
      closing: p.closing,
      status: p.status,
      dailyUsage: p.dailyUsage
    };
    
    // Get sectors for this period
    const period_sectors = get_sectors_for_period(target_period_idx);
    
    let html = `<h3 style="color: #28a745; margin-top: 0;">⏭️ Updating Period ${target_period_idx + 1}</h3>`;
    
    // Show BEFORE state
    html += '<div style="margin-bottom: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
    html += '<h4 style="color: #856404; margin-top: 0;">📋 BEFORE State:</h4>';
    html += `<div style="font-size: 12px; line-height: 1.8;">`;
    html += `<div><strong>Opening:</strong> ${before_state.opening !== null ? format_number(before_state.opening) + ' L' : 'null'}</div>`;
    html += `<div><strong>Usage:</strong> ${before_state.usage !== null ? format_number(before_state.usage) + ' L' : 'null'}</div>`;
    if(p.original_provisional_usage !== null && p.original_provisional_usage !== undefined){
      html += `<div><strong>Original PROVISIONAL Usage:</strong> <span style="color: #856404; font-weight: bold;">${format_number(p.original_provisional_usage)} L</span></div>`;
    }
    html += `<div><strong>Closing:</strong> ${before_state.closing !== null ? format_number(before_state.closing) + ' L' : 'null'}</div>`;
    html += `<div><strong>Status:</strong> ${before_state.status}</div>`;
    html += `<div><strong>Daily Usage:</strong> ${before_state.dailyUsage !== null ? before_state.dailyUsage.toFixed(2) + ' L/day' : 'null'}</div>`;
    html += `</div></div>`;
    
    // Show sector breakdown
    html += '<div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #17a2b8; border-radius: 4px;">';
    html += '<h4 style="color: #004085; margin-top: 0;">🔷 Sector Breakdown for Period ' + (target_period_idx + 1) + ':</h4>';
    if(period_sectors.length === 0){
      html += '<div style="color: #dc3545;">No sectors found for this period</div>';
    } else {
      html += '<table style="width: 100%; border-collapse: collapse; margin-top: 8px;">';
      html += '<tr style="background: #cfe2ff;"><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Sector</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Days</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Usage (L)</th><th style="padding: 6px; border: 1px solid #b6d4fe; font-size: 11px;">Daily Usage</th></tr>';
      let total_days = 0;
      let total_usage = 0;
      period_sectors.forEach(s => {
        const days = s.days_in_period ?? s.sector_days ?? 0;
        const usage = s.usage_in_period ?? s.total_usage ?? s.sector_usage ?? 0;
        total_days += days;
        total_usage += usage;
        const sector_label = s.sub_id ? `Sector ${s.sub_id}` : `Sector ${s.sector_id || 'N/A'}`;
        html += `<tr style="background: #fff;">
          <td style="padding: 6px; border: 1px solid #b6d4fe; font-weight: bold;">${sector_label}</td>
          <td style="padding: 6px; border: 1px solid #b6d4fe;">${days} days</td>
          <td style="padding: 6px; border: 1px solid #b6d4fe;">${format_number(usage)} L</td>
          <td style="padding: 6px; border: 1px solid #b6d4fe;">${s.daily_usage ? s.daily_usage.toFixed(2) + ' L/day' : 'N/A'}</td>
        </tr>`;
      });
      html += `<tr style="background: #d1ecf1; font-weight: bold;">
        <td style="padding: 6px; border: 1px solid #bee5eb;">TOTAL</td>
        <td style="padding: 6px; border: 1px solid #bee5eb;">${total_days} days</td>
        <td style="padding: 6px; border: 1px solid #bee5eb;">${format_number(total_usage)} L</td>
        <td style="padding: 6px; border: 1px solid #bee5eb;">${total_days > 0 ? (total_usage / total_days).toFixed(2) + ' L/day' : 'N/A'}</td>
      </tr>`;
      html += '</table>';
    }
    html += '</div>';
    
    // Calculate and update the period
    const end_display = new Date(p.end);
    end_display.setDate(end_display.getDate() - 1);
    const period_days = days_between(p.start, end_display);
    
    // Check if there's a reading after period end
    const all_readings = get_all_readings_sorted();
    // Compare Date objects: p.end is exclusive (start of next period at 00:00:00)
    // A reading on or after p.end should trigger CALCULATED status
    const periodEndDate = new Date(p.end);
    periodEndDate.setHours(0, 0, 0, 0); // Normalize to start of day
    const reading_after_period_end = all_readings.find(r => {
      const readingDate = new Date(r.date);
      readingDate.setHours(12, 0, 0, 0); // Normalize reading date to noon for comparison
      return readingDate >= periodEndDate; // >= because p.end is exclusive
    });
    
    if(reading_after_period_end && (p.status === "PROVISIONAL" || p.status === "OPEN")){
      // Use sector-based calculation
      recalculate_period_from_sectors(target_period_idx);
    } else {
      // Use standard calculation
      const readings = p.readings
        .filter(r=>r.date&&r.value!==null)
        .map(r=>({d:new Date(r.date),v:r.value}))
        .sort((a,b)=>a.d-b.d);
      
      if(target_period_idx === 0){
          if(readings.length >= 2){
            const r_last = readings.at(-1);
            const days = days_between(readings[0].d, r_last.d);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.start_reading) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.start_reading + p.usage;
              // Check against end_display (actual last day), not p.end (exclusive)
              const lastReadingDate = new Date(r_last.d);
              lastReadingDate.setHours(12, 0, 0, 0);
              const endDisplayNorm = new Date(end_display);
              endDisplayNorm.setHours(12, 0, 0, 0);
              p.status = lastReadingDate.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
            }
          }
        } else {
          if(readings.length >= 1){
            const r_last = readings.at(-1);
            const co_reading_date = new Date(p.start);
            co_reading_date.setHours(12, 0, 0, 0);
            const last_reading_date = new Date(r_last.d);
            last_reading_date.setHours(12, 0, 0, 0);
            const days = days_between(co_reading_date, last_reading_date);
            if(days > 0){
              p.dailyUsage = (r_last.v - p.opening) / days;
              p.usage = p.dailyUsage * period_days;
              p.closing = p.opening + p.usage;
              // Check against end_display (actual last day), not p.end (exclusive)
              const lastReadingDateNorm = new Date(r_last.d);
              lastReadingDateNorm.setHours(12, 0, 0, 0);
              const endDisplayNorm = new Date(end_display);
              endDisplayNorm.setHours(12, 0, 0, 0);
              p.status = lastReadingDateNorm.getTime() === endDisplayNorm.getTime() ? "ACTUAL" : "PROVISIONAL";
            }
        } else {
          // No readings - use previous period's dailyUsage
          const prev_period = periods[target_period_idx - 1];
          if (prev_period && prev_period.dailyUsage !== null && prev_period.dailyUsage !== undefined && prev_period.dailyUsage > 0) {
            p.dailyUsage = prev_period.dailyUsage;
            p.usage = p.dailyUsage * period_days;
            p.closing = p.opening + p.usage;
            p.status = "PROVISIONAL";
          }
        }
      }
      p.sectors = period_sectors;
    }
    
    // Update opening from previous period
    if(target_period_idx > 0){
      const prev = periods[target_period_idx - 1];
      p.opening = prev.closing;
    }
    
    // Show AFTER state
    html += '<div style="margin-bottom: 15px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">';
    html += '<h4 style="color: #155724; margin-top: 0;">✅ AFTER State:</h4>';
    html += `<div style="font-size: 12px; line-height: 1.8;">`;
    
    const changes = [];
    if(before_state.opening !== p.opening){
      changes.push('opening');
      html += `<div><strong>Opening:</strong> <span style="color: #dc3545; text-decoration: line-through;">${before_state.opening !== null ? format_number(before_state.opening) + ' L' : 'null'}</span> → <span style="color: #28a745; font-weight: bold;">${p.opening !== null ? format_number(p.opening) + ' L' : 'null'}</span></div>`;
    } else {
      html += `<div><strong>Opening:</strong> ${p.opening !== null ? format_number(p.opening) + ' L' : 'null'}</div>`;
    }
    
    if(before_state.usage !== p.usage){
      changes.push('usage');
      const original_display = p.original_provisional_usage !== null && p.original_provisional_usage !== undefined 
        ? ` (Original PROVISIONAL: ${format_number(p.original_provisional_usage)} L)`
        : '';
      html += `<div><strong>Usage:</strong> <span style="color: #dc3545; text-decoration: line-through;">${before_state.usage !== null ? format_number(before_state.usage) + ' L' : 'null'}</span> → <span style="color: #28a745; font-weight: bold;">${p.usage !== null ? format_number(p.usage) + ' L' : 'null'}</span>${original_display}</div>`;
    } else {
      html += `<div><strong>Usage:</strong> ${p.usage !== null ? format_number(p.usage) + ' L' : 'null'}</div>`;
      if(p.original_provisional_usage !== null && p.original_provisional_usage !== undefined && p.original_provisional_usage !== p.usage){
        html += `<div style="font-size: 11px; color: #856404; margin-left: 10px;">(Original PROVISIONAL was: ${format_number(p.original_provisional_usage)} L)</div>`;
      }
    }
    
    if(before_state.closing !== p.closing){
      changes.push('closing');
      html += `<div><strong>Closing:</strong> <span style="color: #dc3545; text-decoration: line-through;">${before_state.closing !== null ? format_number(before_state.closing) + ' L' : 'null'}</span> → <span style="color: #28a745; font-weight: bold;">${p.closing !== null ? format_number(p.closing) + ' L' : 'null'}</span></div>`;
    } else {
      html += `<div><strong>Closing:</strong> ${p.closing !== null ? format_number(p.closing) + ' L' : 'null'}</div>`;
    }
    
    if(before_state.status !== p.status){
      changes.push('status');
      html += `<div><strong>Status:</strong> <span style="color: #dc3545; text-decoration: line-through;">${before_state.status}</span> → <span style="color: #28a745; font-weight: bold;">${p.status}</span></div>`;
    } else {
      html += `<div><strong>Status:</strong> ${p.status}</div>`;
    }
    
    html += `<div><strong>Daily Usage:</strong> ${p.dailyUsage !== null ? p.dailyUsage.toFixed(2) + ' L/day' : 'null'}</div>`;
    html += `</div>`;
    
    if(changes.length > 0){
      html += `<div style="margin-top: 10px; padding: 8px; background: #fff3cd; border-radius: 4px; color: #856404; font-weight: bold;">⚠️ Changes detected: ${changes.join(', ')}</div>`;
    } else {
      html += `<div style="margin-top: 10px; padding: 8px; background: #d1ecf1; border-radius: 4px; color: #0c5460;">ℹ️ No changes detected</div>`;
    }
    
    html += '</div>';
    
    debugOutput.innerHTML = html;
    
    // Re-render the main table
    render();
    if(active === target_period_idx){
      render_calculation_output();
    }
  } catch (error) {
    log_error(error.message);
    console.error("Error in next_period_update:", error);
  }
}

/* ==================== COPY OUTPUT FUNCTION ==================== */
function copy_output_to_clipboard(){
  try {
    // Check for both container IDs (legacy and new)
    let outputContainer = document.getElementById("period_output_container");
    if (!outputContainer) {
      outputContainer = document.getElementById("output_container");
    }
    if (!outputContainer) {
      // Silently return if container not found (might be in Date to Date mode)
      return;
    }
    
    if(active === null) {
      log_error("No period selected");
      return;
    }
    
    const p = periods[active];
    // Period end is exclusive, so calculate days from start to (end - 1 day) for actual period span
    const period_end_display = new Date(p.end);
    period_end_display.setDate(period_end_display.getDate() - 1);
    const period_days = days_between(p.start, period_end_display);
    
    // Build plain text output for copying
    let text = `=== PERIOD ${active + 1} CALCULATION ===\n\n`;
    
    text += `PERIOD INFORMATION:\n`;
    text += `Start (inclusive):     ${iso(p.start)}\n`;
    text += `End (exclusive):       ${iso(p.end)}\n`;
    text += `Period Days:           ${period_days}\n`;
    text += `Period_Status:         ${p.status}\n\n`;
    
    text += `READINGS:\n`;
    const validReadings = p.readings.filter(r => r.date && r.value !== null);
    validReadings.forEach((r, i) => {
      text += `  Reading ${i+1}:        ${r.date} = ${r.value} L\n`;
    });
    text += `\n`;
    
    if (active === 0 && p.start_reading !== null && p.start_reading !== undefined) {
      text += `Start Reading (Period 1)\n`;
      text += `START_READING:         ${p.start_reading} L\n`;
      text += `(Period 1 baseline - never reused)\n\n`;
    } else if (active > 0 && p.opening !== null && p.opening !== undefined) {
      text += `Opening Reading\n`;
      text += `OPENING_READING:       ${p.opening.toFixed(0)} L\n`;
      
      // Show adjustment if previous period was recalculated
      const prev = periods[active - 1];
      if(prev.status === "CALCULATED" && prev.adjustment !== null && prev.adjustment !== undefined){
        text += `(From Period ${active}'s CALCULATED Closing_Reading: ${(prev.calculated_closing ?? prev.closing).toFixed(0)} L)\n`;
        text += `Previous Period Adjustment:${prev.adjustment >= 0 ? '+' : ''}${prev.adjustment.toFixed(0)} L\n`;
        text += `(Difference: CALCULATED ${(prev.calculated_closing ?? prev.closing).toFixed(0)} - PROVISIONED ${(prev.provisioned_closing ?? prev.closing).toFixed(0)})\n\n`;
      } else {
        text += `(Carried forward from Period ${active}'s Closing_Reading)\n\n`;
      }
    }
    
    // CO Reading (Closing Opening) Section
    if (p.closing !== null && p.closing !== undefined) {
      text += `CO Reading (Closing Opening)\n`;
      
      // Show PROVISIONED and CALCULATED if period was recalculated
      if(p.status === "CALCULATED" && p.provisioned_closing !== null && p.provisioned_closing !== undefined){
        text += `PROVISIONED (Original):${p.provisioned_closing.toFixed(0)} L\n`;
        text += `(Immutable - original billable amount)\n`;
        text += `CALCULATED (Corrected):${(p.calculated_closing ?? p.closing).toFixed(0)} L\n`;
        text += `(Recalculated from sectors after late reading)\n`;
        if(p.adjustment !== null && p.adjustment !== undefined){
          const adjustment_sign = p.adjustment >= 0 ? '+' : '';
          text += `Adjustment:${adjustment_sign}${p.adjustment.toFixed(0)} L\n`;
          text += `(Carried forward to next period)\n`;
        }
      } else {
        text += `CLOSING_READING:       ${p.closing.toFixed(0)} L\n`;
        text += `${active === 0 ? '(Calculated from START_READING + Period_Total_Usage)' : '(Calculated from OPENING_READING + Period_Total_Usage)'}\n`;
      }
      text += `\n`;
    }
    
    if (p.usage !== null && p.usage !== undefined) {
      const daily_usage = p.dailyUsage || (p.usage / period_days);
      
      text += `USAGE CALCULATION:\n`;
      text += `Period_Total_Usage:    ${p.usage.toFixed(0)} L\n`;
      text += `Daily_Usage:           ${daily_usage.toFixed(2)} L/day\n`;
      text += `Closing_Reading:       ${p.closing.toFixed(0)} L\n\n`;
      
      // Sector Breakdown
      if(p.sectors && p.sectors.length > 0){
        text += `SECTOR BREAKDOWN:\n`;
        p.sectors.forEach(s => {
          const sector_label = (s.sub_id != null && typeof s.sub_id === 'string' && s.sub_id !== 'NaN') 
            ? `Sector ${s.sub_id}` 
            : `Sector ${s.sector_id}`;
          const total_usage = s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0;
          const days_in_period = s.days_in_period ?? s.sector_days ?? 0;
          const daily_usage = s.daily_usage ?? s.sector_daily_usage ?? 0;
          
          text += `  ${sector_label}\n`;
          text += `    Start Date:        ${iso(s.start_date)}\n`;
          text += `    End Date:          ${iso(s.end_date)}\n`;
          text += `    Start Reading:     ${(s.start_reading ?? 0).toFixed(0)} L\n`;
          text += `    End Reading:       ${(s.end_reading ?? 0).toFixed(0)} L\n`;
          text += `    Total Usage:       ${total_usage.toFixed(0)} L\n`;
          text += `    Daily Usage:       ${daily_usage.toFixed(2)} L/day\n`;
          text += `    Days in Period:    ${days_in_period} days\n`;
        });
        text += `\n`;
        
        // Validation
        let total_sector_days = 0;
        let total_sector_usage = 0;
        p.sectors.forEach(s => {
          total_sector_days += (s.days_in_period ?? s.sector_days ?? 0);
          total_sector_usage += (s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0);
        });
        
        const weighted_sector_daily_usage = total_sector_days > 0 ? total_sector_usage / total_sector_days : 0;
        const period_daily_usage = p.dailyUsage || (p.usage / period_days);
        const daily_usage_match = Math.abs(weighted_sector_daily_usage - period_daily_usage) < 0.01;
        
        let validation_note = "";
        if(p.status === "ACTUAL"){
          validation_note = " (Sectors validate ACTUAL period - calculation not overridden)";
        } else if(p.status === "CALCULATED"){
          validation_note = " (Sectors used for recalculation from PROVISIONAL to CALCULATED)";
        } else {
          validation_note = " (Sectors validate PROVISIONAL period - only daily_usage is validated)";
        }
        
        if(daily_usage_match){
          if(p.status === "PROVISIONAL"){
            text += `✓ Validation Passed: Daily Usage matches (${weighted_sector_daily_usage.toFixed(2)} L/day = ${period_daily_usage.toFixed(2)} L/day). Sector days (${total_sector_days}) and usage (${total_sector_usage.toFixed(0)} L) are actual values, period values are projected${validation_note}\n\n`;
          } else {
            text += `✓ Validation Passed: Sum of sector days (${total_sector_days}) = Period Days (${period_days}), Sum of sector usage (${total_sector_usage.toFixed(0)} L) = Period Total Usage (${p.usage.toFixed(0)} L)${validation_note}\n\n`;
          }
        } else {
          const failures = [];
          if(p.status !== "PROVISIONAL"){
            if(total_sector_days !== period_days) failures.push(`Days match: false (${total_sector_days} vs ${period_days})`);
            if(Math.abs(total_sector_usage - p.usage) > 0.01) failures.push(`Usage match: false (${total_sector_usage.toFixed(0)} vs ${p.usage.toFixed(0)})`);
          }
          if(!daily_usage_match) failures.push(`Daily Usage match: false (${weighted_sector_daily_usage.toFixed(2)} vs ${period_daily_usage.toFixed(2)})`);
          
          text += `⚠ Validation Failed: ${failures.join(", ")}${validation_note}\n\n`;
        }
      }
      
      // Calculate tier charges
      const tiers = get_tiers();
      let remaining = p.usage;
      let prev = 0;
      let total = 0;
      const tier_lines = [];
      
      for(const t of tiers){
        const cap = t.max - prev;
        const used = Math.max(0, Math.min(remaining, cap));
        if(used > 0){
          const cost = (used / 1000) * t.rate;
          tier_lines.push(`Tier ${prev}–${t.max} L:     ${used.toFixed(0)} L @ R${t.rate}/kL = R${cost.toFixed(2)}`);
          total += cost;
          remaining -= used;
        }
        prev = t.max;
      }
      
      const daily_cost = total / period_days;
      
      text += `Tier Charges\n`;
      tier_lines.forEach(line => {
        text += `${line}\n`;
      });
      text += `\n`;
      
      // Reconciliation Tier Cost Section - Show ALL consecutive recalculated periods
      // Only shown in the period where the reading was done (immediately after recalculated periods)
      if(active > 0){
        // Find consecutive recalculated periods ending just before the current period
        let consecutive_recalculated = [];
        for(let i = active - 1; i >= 0; i--){
          const check_period = periods[i];
          if(check_period.status === "CALCULATED" && 
             check_period.original_provisional_usage !== null && 
             check_period.original_provisional_usage !== undefined &&
             check_period.usage !== null && 
             check_period.usage !== undefined){
            consecutive_recalculated.unshift(i); // Add to front to maintain order
          } else {
            // Stop if we hit a non-recalculated period (not consecutive)
            break;
          }
        }
        
        // Only show reconciliation in the period immediately after the consecutive recalculated periods
        // AND only if the current period is NOT CALCULATED (it's PROVISIONAL or ACTUAL)
        if(consecutive_recalculated.length > 0 && 
           active === consecutive_recalculated[consecutive_recalculated.length - 1] + 1 &&
           p.status !== "CALCULATED"){
          consecutive_recalculated.forEach(period_idx => {
            const check_period = periods[period_idx];
            
            // Calculate usage amounts
            const provisioned_usage = check_period.original_provisional_usage;
            const calculated_usage = check_period.usage;
            const adjustment_litres = calculated_usage - provisioned_usage;
            
            const reconciliation_cost = calculate_reconciliation_tier_cost(
              adjustment_litres, 
              provisioned_usage, 
              calculated_usage
            );
            
            if(reconciliation_cost !== null){
              const period_num = period_idx + 1;
              text += `Reconciliation Tier Cost (Period ${period_num})\n`;
              text += `Reconciliation Period:Period ${period_num}\n`;
              text += `(Reconciliation for Period ${period_num} displayed in Period ${active + 1})\n`;
              text += `Reconciliation Amount:${adjustment_litres >= 0 ? '+' : ''}${adjustment_litres.toFixed(0)} L\n`;
              text += `(CALCULATED Usage ${reconciliation_cost.calculated_litres.toFixed(0)} L - PROVISIONED Usage ${reconciliation_cost.provisioned_litres.toFixed(0)} L)\n`;
              text += `Reconciliation Tier Cost:R${reconciliation_cost.total_cost >= 0 ? '' : '-'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}\n`;
              text += `(Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)} - Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)})\n`;
              text += `1️⃣ PROVISIONED Usage (${reconciliation_cost.provisioned_litres.toFixed(0)} L) - Original Bill (To Credit):\n`;
              if(reconciliation_cost.provisioned_breakdown.length > 0){
                reconciliation_cost.provisioned_breakdown.forEach(item => {
                  text += `Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L: ${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}\n`;
                });
              } else {
                text += `No tier allocation (0 L)\n`;
              }
              text += `Total Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}\n`;
              text += `2️⃣ CALCULATED Usage (${reconciliation_cost.calculated_litres.toFixed(0)} L) - Corrected Bill (To Charge):\n`;
              if(reconciliation_cost.calculated_breakdown.length > 0){
                reconciliation_cost.calculated_breakdown.forEach(item => {
                  text += `Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L: ${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}\n`;
                });
              } else {
                text += `No tier allocation (0 L)\n`;
              }
              text += `Total Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}\n`;
              text += `3️⃣ Reconciliation Cost Calculation:\n`;
              text += `Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}\n`;
              text += `Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}\n`;
              text += `Reconciliation Cost = R${reconciliation_cost.calculated_cost.toFixed(2)} - R${reconciliation_cost.provisioned_cost.toFixed(2)} = ${reconciliation_cost.total_cost >= 0 ? 'R' : '-R'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}\n\n`;
            }
          });
        }
      }
      
      text += `Cost Summary\n`;
      text += `TOTAL COST:            R${total.toFixed(2)}\n`;
      text += `Daily Cost:            R${daily_cost.toFixed(2)}/day\n`;
    } else {
      text += `USAGE: Not calculated (need at least 2 readings)\n`;
    }
    
    // Copy to clipboard
    navigator.clipboard.writeText(text).then(() => {
      const copyBtn = document.getElementById("copy_output");
      if (copyBtn) {
        const originalText = copyBtn.textContent;
        copyBtn.textContent = "✅ Copied!";
        copyBtn.style.background = "#28a745";
        setTimeout(() => {
          copyBtn.textContent = originalText;
          copyBtn.style.background = "#007bff";
        }, 2000);
      }
    }).catch(err => {
      log_error("Failed to copy: " + err.message);
    });
  } catch (error) {
    log_error(error.message);
    console.error("Error in copy_output_to_clipboard:", error);
  }
}

function copy_all_periods_to_clipboard(){
  try {
    if (periods.length === 0) {
      alert('No periods to copy');
      return;
    }
    
    let text = '';
    const tiers = get_tiers();
    
    periods.forEach((p, periodIndex) => {
      // Period end is exclusive, so calculate days from start to (end - 1 day) for actual period span
      const period_end_display = new Date(p.end);
      period_end_display.setDate(period_end_display.getDate() - 1);
      const period_days = days_between(p.start, period_end_display);
      
      text += `=== PERIOD ${periodIndex + 1} CALCULATION ===\n\n`;
      
      text += `PERIOD INFORMATION:\n`;
      text += `Start (inclusive):     ${iso(p.start)}\n`;
      text += `End (exclusive):       ${iso(p.end)}\n`;
      text += `Period Days:           ${period_days}\n`;
      text += `Period_Status:         ${p.status}\n\n`;
      
      text += `READINGS:\n`;
      const validReadings = p.readings.filter(r => r.date && r.value !== null);
      if (validReadings.length > 0) {
        validReadings.forEach((r, i) => {
          text += `  Reading ${i+1}:        ${r.date} = ${r.value} L\n`;
        });
      } else {
        text += `  (No readings)\n`;
      }
      text += `\n`;
      
      if (periodIndex === 0 && p.start_reading !== null && p.start_reading !== undefined) {
        text += `Start Reading (Period 1)\n`;
        text += `START_READING:         ${p.start_reading} L\n`;
        text += `(Period 1 baseline - never reused)\n\n`;
      } else if (periodIndex > 0 && p.opening !== null && p.opening !== undefined) {
        text += `Opening Reading\n`;
        text += `OPENING_READING:       ${p.opening.toFixed(0)} L\n`;
        
        // Show adjustment if previous period was recalculated
        const prev = periods[periodIndex - 1];
        if(prev.status === "CALCULATED" && prev.adjustment !== null && prev.adjustment !== undefined){
          text += `(From Period ${periodIndex}'s CALCULATED Closing_Reading: ${(prev.calculated_closing ?? prev.closing).toFixed(0)} L)\n`;
          text += `Previous Period Adjustment:${prev.adjustment >= 0 ? '+' : ''}${prev.adjustment.toFixed(0)} L\n`;
          text += `(Difference: CALCULATED ${(prev.calculated_closing ?? prev.closing).toFixed(0)} - PROVISIONED ${(prev.provisioned_closing ?? prev.closing).toFixed(0)})\n\n`;
        } else {
          text += `(Carried forward from Period ${periodIndex}'s Closing_Reading)\n\n`;
        }
      }
      
      // CO Reading (Closing Opening) Section
      if (p.closing !== null && p.closing !== undefined) {
        text += `CO Reading (Closing Opening)\n`;
        
        // Show PROVISIONED and CALCULATED if period was recalculated
        if(p.status === "CALCULATED" && p.provisioned_closing !== null && p.provisioned_closing !== undefined){
          text += `PROVISIONED (Original):${p.provisioned_closing.toFixed(0)} L\n`;
          text += `(Immutable - original billable amount)\n`;
          text += `CALCULATED (Corrected):${(p.calculated_closing ?? p.closing).toFixed(0)} L\n`;
          text += `(Recalculated from sectors after late reading)\n`;
          if(p.adjustment !== null && p.adjustment !== undefined){
            const adjustment_sign = p.adjustment >= 0 ? '+' : '';
            text += `Adjustment:${adjustment_sign}${p.adjustment.toFixed(0)} L\n`;
            text += `(Carried forward to next period)\n`;
          }
        } else {
          text += `CLOSING_READING:       ${p.closing.toFixed(0)} L\n`;
          text += `${periodIndex === 0 ? '(Calculated from START_READING + Period_Total_Usage)' : '(Calculated from OPENING_READING + Period_Total_Usage)'}\n`;
        }
        text += `\n`;
      }
      
      if (p.usage !== null && p.usage !== undefined) {
        const daily_usage = p.dailyUsage || (p.usage / period_days);
        
        text += `USAGE CALCULATION:\n`;
        text += `Period_Total_Usage:    ${p.usage.toFixed(0)} L\n`;
        text += `Daily_Usage:           ${daily_usage.toFixed(2)} L/day\n`;
        text += `Closing_Reading:       ${p.closing.toFixed(0)} L\n\n`;
        
        // Sector Breakdown
        if(p.sectors && p.sectors.length > 0){
          text += `SECTOR BREAKDOWN:\n`;
          p.sectors.forEach(s => {
            const sector_label = (s.sub_id != null && typeof s.sub_id === 'string' && s.sub_id !== 'NaN') 
              ? `Sector ${s.sub_id}` 
              : `Sector ${s.sector_id}`;
            const total_usage = s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0;
            const days_in_period = s.days_in_period ?? s.sector_days ?? 0;
            const daily_usage = s.daily_usage ?? s.sector_daily_usage ?? 0;
            
            text += `  ${sector_label}\n`;
            text += `    Start Date:        ${iso(s.start_date)}\n`;
            text += `    End Date:          ${iso(s.end_date)}\n`;
            text += `    Start Reading:     ${(s.start_reading ?? 0).toFixed(0)} L\n`;
            text += `    End Reading:       ${(s.end_reading ?? 0).toFixed(0)} L\n`;
            text += `    Total Usage:       ${total_usage.toFixed(0)} L\n`;
            text += `    Daily Usage:       ${daily_usage.toFixed(2)} L/day\n`;
            text += `    Days in Period:    ${days_in_period} days\n`;
          });
          text += `\n`;
          
          // Validation
          let total_sector_days = 0;
          let total_sector_usage = 0;
          p.sectors.forEach(s => {
            total_sector_days += (s.days_in_period ?? s.sector_days ?? 0);
            total_sector_usage += (s.total_usage ?? s.usage_in_period ?? s.sector_usage ?? 0);
          });
          
          const weighted_sector_daily_usage = total_sector_days > 0 ? total_sector_usage / total_sector_days : 0;
          const period_daily_usage = p.dailyUsage || (p.usage / period_days);
          const daily_usage_match = Math.abs(weighted_sector_daily_usage - period_daily_usage) < 0.01;
          
          let validation_note = "";
          if(p.status === "ACTUAL"){
            validation_note = " (Sectors validate ACTUAL period - calculation not overridden)";
          } else if(p.status === "CALCULATED"){
            validation_note = " (Sectors used for recalculation from PROVISIONAL to CALCULATED)";
          } else {
            validation_note = " (Sectors validate PROVISIONAL period - only daily_usage is validated)";
          }
          
          if(daily_usage_match){
            if(p.status === "PROVISIONAL"){
              text += `✓ Validation Passed: Daily Usage matches (${weighted_sector_daily_usage.toFixed(2)} L/day = ${period_daily_usage.toFixed(2)} L/day). Sector days (${total_sector_days}) and usage (${total_sector_usage.toFixed(0)} L) are actual values, period values are projected${validation_note}\n\n`;
            } else {
              text += `✓ Validation Passed: Sum of sector days (${total_sector_days}) = Period Days (${period_days}), Sum of sector usage (${total_sector_usage.toFixed(0)} L) = Period Total Usage (${p.usage.toFixed(0)} L)${validation_note}\n\n`;
            }
          } else {
            const failures = [];
            if(p.status !== "PROVISIONAL"){
              if(total_sector_days !== period_days) failures.push(`Days match: false (${total_sector_days} vs ${period_days})`);
              if(Math.abs(total_sector_usage - p.usage) > 0.01) failures.push(`Usage match: false (${total_sector_usage.toFixed(0)} vs ${p.usage.toFixed(0)})`);
            }
            if(!daily_usage_match) failures.push(`Daily Usage match: false (${weighted_sector_daily_usage.toFixed(2)} vs ${period_daily_usage.toFixed(2)})`);
            
            text += `⚠ Validation Failed: ${failures.join(", ")}${validation_note}\n\n`;
          }
        }
        
        // Calculate tier charges
        let remaining = p.usage;
        let prev = 0;
        let total = 0;
        const tier_lines = [];
        
        for(const t of tiers){
          const cap = t.max - prev;
          const used = Math.max(0, Math.min(remaining, cap));
          if(used > 0){
            const cost = (used / 1000) * t.rate;
            tier_lines.push(`Tier ${prev}–${t.max} L:     ${used.toFixed(0)} L @ R${t.rate}/kL = R${cost.toFixed(2)}`);
            total += cost;
            remaining -= used;
          }
          prev = t.max;
        }
        
        const daily_cost = total / period_days;
        
        text += `Tier Charges\n`;
        tier_lines.forEach(line => {
          text += `${line}\n`;
        });
        text += `\n`;
        
        // Reconciliation Tier Cost Section - Show ALL consecutive recalculated periods
        // Only shown in the period where the reading was done (immediately after recalculated periods)
        if(periodIndex > 0){
          // Find consecutive recalculated periods ending just before the current period
          let consecutive_recalculated = [];
          for(let i = periodIndex - 1; i >= 0; i--){
            const check_period = periods[i];
            if(check_period.status === "CALCULATED" && 
               check_period.adjustment !== null && check_period.adjustment !== undefined && 
               check_period.provisioned_closing !== null && check_period.provisioned_closing !== undefined){
              consecutive_recalculated.unshift(i); // Add to front to maintain order
            } else {
              // Stop if we hit a non-recalculated period (not consecutive)
              break;
            }
          }
          
          // Only show reconciliation in the period immediately after the consecutive recalculated periods
          // AND only if the current period is NOT CALCULATED (it's PROVISIONAL or ACTUAL)
          if(consecutive_recalculated.length > 0 && 
             periodIndex === consecutive_recalculated[consecutive_recalculated.length - 1] + 1 &&
             p.status !== "CALCULATED"){
            consecutive_recalculated.forEach(period_idx => {
              const check_period = periods[period_idx];
              
              // Calculate usage amounts from closing readings
              let opening_reading;
              if(period_idx === 0){
                opening_reading = check_period.start_reading || 0;
              } else {
                const prev_period = periods[period_idx - 1];
                opening_reading = (prev_period.calculated_closing !== null && prev_period.calculated_closing !== undefined)
                  ? prev_period.calculated_closing
                  : prev_period.closing;
              }
              
              const provisioned_usage = check_period.provisioned_closing - opening_reading;
              const calculated_usage = (check_period.calculated_closing ?? check_period.closing) - opening_reading;
              
              const reconciliation_cost = calculate_reconciliation_tier_cost(
                check_period.adjustment, 
                provisioned_usage, 
                calculated_usage
              );
              
              if(reconciliation_cost !== null){
                const period_num = period_idx + 1;
                text += `Reconciliation Tier Cost (Period ${period_num})\n`;
                text += `Reconciliation Period:Period ${period_num}\n`;
                text += `(Reconciliation for Period ${period_num} displayed in Period ${periodIndex + 1})\n`;
                text += `Reconciliation Amount:${check_period.adjustment >= 0 ? '+' : ''}${check_period.adjustment.toFixed(0)} L\n`;
                text += `(CALCULATED Usage ${reconciliation_cost.calculated_litres.toFixed(0)} L - PROVISIONED Usage ${reconciliation_cost.provisioned_litres.toFixed(0)} L)\n`;
                text += `Reconciliation Tier Cost:R${reconciliation_cost.total_cost >= 0 ? '' : '-'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}\n`;
                text += `(Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)} - Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)})\n`;
                text += `1️⃣ PROVISIONED Usage (${reconciliation_cost.provisioned_litres.toFixed(0)} L) - Original Bill (To Credit):\n`;
                if(reconciliation_cost.provisioned_breakdown.length > 0){
                  reconciliation_cost.provisioned_breakdown.forEach(item => {
                    text += `Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L: ${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}\n`;
                  });
                } else {
                  text += `No tier allocation (0 L)\n`;
                }
                text += `Total Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}\n`;
                text += `2️⃣ CALCULATED Usage (${reconciliation_cost.calculated_litres.toFixed(0)} L) - Corrected Bill (To Charge):\n`;
                if(reconciliation_cost.calculated_breakdown.length > 0){
                  reconciliation_cost.calculated_breakdown.forEach(item => {
                    text += `Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L: ${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}\n`;
                  });
                } else {
                  text += `No tier allocation (0 L)\n`;
                }
                text += `Total Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}\n`;
                text += `3️⃣ Reconciliation Cost Calculation:\n`;
                text += `Calculated Cost: R${reconciliation_cost.calculated_cost.toFixed(2)}\n`;
                text += `Provisioned Cost: R${reconciliation_cost.provisioned_cost.toFixed(2)}\n`;
                text += `Reconciliation Cost = R${reconciliation_cost.calculated_cost.toFixed(2)} - R${reconciliation_cost.provisioned_cost.toFixed(2)} = ${reconciliation_cost.total_cost >= 0 ? 'R' : '-R'}${Math.abs(reconciliation_cost.total_cost).toFixed(2)}\n\n`;
              }
            });
          }
        }
        
        text += `Cost Summary\n`;
        text += `TOTAL COST:            R${total.toFixed(2)}\n`;
        text += `Daily Cost:            R${daily_cost.toFixed(2)}/day\n`;
      } else {
        text += `USAGE: Not calculated (need at least 2 readings)\n`;
      }
      
      text += `\n`;
    });
    
    // Copy to clipboard
    navigator.clipboard.writeText(text).then(() => {
      const copyBtn = document.getElementById("copy_all_periods");
      if (copyBtn) {
        const originalText = copyBtn.textContent;
        copyBtn.textContent = "✅ Copied!";
        copyBtn.style.background = "#28a745";
        setTimeout(() => {
          copyBtn.textContent = originalText;
          copyBtn.style.background = "#6c757d";
        }, 2000);
      }
    }).catch(err => {
      log_error("Failed to copy all periods: " + err.message);
    });
  } catch (error) {
    log_error(error.message);
    console.error("Error in copy_all_periods_to_clipboard:", error);
  }
}

/* ==================== UI MODULE: RENDER DEBUG ==================== */
function render_debug(){
  try {
    // Check for both container IDs (legacy and new)
    let outputContainer = document.getElementById("period_output_container");
    if (!outputContainer) {
      outputContainer = document.getElementById("output_container");
    }
    if (!outputContainer) {
      // Silently return if container not found (might be in Date to Date mode)
      return;
    }
    
    if(active===null){ 
      outputContainer.innerHTML = ""; 
      return; 
    }
    
    const p = periods[active];
    const display_end = new Date(p.end);
    display_end.setDate(display_end.getDate() - 1);
    // Period end is exclusive, so calculate days from start to (end - 1 day) for actual period span
    const period_days = days_between(p.start, display_end);
    
    outputContainer.innerHTML = `<div class="output-section">
      <div class="output-header">Debug Information</div>
      <div class="output-content">
        <div><span class="output-label">Period Start (inclusive):</span><span class="output-value">${iso(p.start)}</span></div>
        <div><span class="output-label">Period End (exclusive):</span><span class="output-value">${iso(p.end)}</span></div>
        <div><span class="output-label">Displayed End:</span><span class="output-value">${iso(display_end)}</span></div>
        <div><span class="output-label">Period Days:</span><span class="output-value">${period_days}</span></div>
        <div><span class="output-label">Readings in period:</span><span class="output-value">${p.readings.length}</span></div>
        <div><span class="output-label">Total periods:</span><span class="output-value">${periods.length}</span></div>
      </div>
    </div>`;
  } catch (error) {
    log_error(error.message);
    console.error("Error in render_debug:", error);
  }
}
// @END_PROTECTED_MODULE: UI_Rev1

/* ==================== CALCULATION EXPLANATIONS ==================== */
const calculationExplanations = {
    period_days: {
        title: "Period Days Calculation",
        description: "The number of days in the billing period (inclusive start to inclusive end).",
        formula: "Period_Days = Days_Between(Period_Start, Period_End_Display)\nwhere Period_End_Display = Period_End - 1 day\n(because Period_End is exclusive)",
        example: "If Period_Start = 2026-01-20 and Period_End = 2026-02-20 (exclusive):\nPeriod_End_Display = 2026-02-19\nPeriod_Days = Days_Between(2026-01-20, 2026-02-19) = 31 days"
    },
    daily_usage: {
        title: "Daily Usage Calculation",
        description: "The average daily consumption rate calculated from readings using reading days (not period days).",
        formula: "Daily_Usage = Actual_Usage ÷ Reading_Days\nwhere Actual_Usage = Last_Reading - Opening_Reading (or Start_Reading for Period 1)\nand Reading_Days = Days_Between(First_Reading_Date, Last_Reading_Date) for Period 1\nor Days_Between(Period_Start_Date, Last_Reading_Date) for Period 2+",
        example: "If Actual_Usage = 10000 L and Reading_Days = 11 (between first and last reading):\nDaily_Usage = 10000 ÷ 11 = 909.09 L/day"
    },
    period_usage: {
        title: "Period Total Usage Calculation",
        description: "The total consumption for the billing period.",
        formula: "Period_Usage = Daily_Usage × Period_Days\nOR\nPeriod_Usage = Last_Reading - Opening_Reading (if ACTUAL status)",
        example: "If Daily_Usage = 100 L/day and Period_Days = 31:\nPeriod_Usage = 100 × 31 = 3100 L"
    },
    closing_reading: {
        title: "Closing Reading Calculation",
        description: "The calculated or actual meter reading at the end of the period.",
        formula: "Closing_Reading = Opening_Reading + Period_Usage\nOR\nClosing_Reading = Last_Reading (if ACTUAL status)",
        example: "If Opening_Reading = 10000 L and Period_Usage = 3100 L:\nClosing_Reading = 10000 + 3100 = 13100 L"
    },
    sector_days: {
        title: "Sector Days Calculation",
        description: "The number of days between two consecutive readings in a sector.",
        formula: "Sector_Days = Days_Between(Sector_Start_Date, Sector_End_Date)\n(inclusive-inclusive)",
        example: "If Sector_Start_Date = 2026-01-20 and Sector_End_Date = 2026-01-25:\nSector_Days = Days_Between(2026-01-20, 2026-01-25) = 6 days"
    },
    sector_daily_usage: {
        title: "Sector Daily Usage Calculation",
        description: "The average daily consumption rate for a specific sector (between two readings).",
        formula: "Sector_Daily_Usage = Sector_Usage ÷ Sector_Days\nwhere Sector_Usage = End_Reading - Start_Reading",
        example: "If Sector_Usage = 600 L and Sector_Days = 6:\nSector_Daily_Usage = 600 ÷ 6 = 100 L/day"
    },
    readings: {
        title: "Readings Calculation",
        description: "Meter readings are user-entered values that represent the meter's display at a specific date.",
        formula: "Usage_Between_Readings = Reading_2 - Reading_1\nDaily_Rate = Usage_Between_Readings ÷ Days_Between(Reading_1_Date, Reading_2_Date)",
        example: "If Reading_1 = 10000 L on 2026-01-20 and Reading_2 = 10300 L on 2026-01-25:\nUsage = 10300 - 10000 = 300 L\nDays = 6 days\nDaily_Rate = 300 ÷ 6 = 50 L/day"
    }
};

/* ==================== CONTEXT MENU ==================== */
let contextMenu = null;

function init_context_menu() {
    contextMenu = document.getElementById('context_menu');
    
    // Hide context menu on click outside
    document.addEventListener('click', () => {
        if (contextMenu) contextMenu.style.display = 'none';
    });
    
    // Prevent default right-click on calculable fields
    document.addEventListener('contextmenu', (e) => {
        const field = e.target.closest('.calculable-field');
        if (field) {
            e.preventDefault();
            show_calculation_explanation(field, e);
        }
    });
}

function show_calculation_explanation(fieldElement, event) {
    const fieldName = fieldElement.getAttribute('data-field');
    const explanation = calculationExplanations[fieldName];
    
    if (!explanation || !contextMenu) return;
    
    const header = document.getElementById('context_menu_header');
    const content = document.getElementById('context_menu_explanation');
    
    if (!header || !content) return;
    
    header.textContent = explanation.title;
    
    let html = `<div style="margin-bottom: 10px;"><strong>Description:</strong><br>${explanation.description}</div>`;
    
    if (explanation.formula) {
        html += `<div class="context-menu-formula"><strong>Formula:</strong><br>${explanation.formula.replace(/\n/g, '<br>')}</div>`;
    }
    
    if (explanation.example) {
        html += `<div style="margin-top: 10px;"><strong>Example:</strong><br><pre style="background: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 11px; margin-top: 5px;">${explanation.example.replace(/\n/g, '\n')}</pre></div>`;
    }
    
    content.innerHTML = html;
    
    // Position context menu
    contextMenu.style.display = 'block';
    contextMenu.style.left = event.pageX + 'px';
    contextMenu.style.top = event.pageY + 'px';
    
    // Adjust if menu goes off screen
    setTimeout(() => {
        const rect = contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            contextMenu.style.left = (event.pageX - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            contextMenu.style.top = (event.pageY - rect.height) + 'px';
        }
    }, 0);
}

/* ==================== GLOBAL WRAPPER FUNCTIONS (Backward Compatibility) ==================== */
// These functions delegate to the containers, maintaining compatibility with HTML onclick handlers
// They will be gradually removed as code is migrated to use containers directly

// Logic wrappers
// Global function - must be accessible from HTML onclick
window.add_period = function() {
  try {
    // Debug: Check if BillingEngineLogic is available
    if (typeof BillingEngineLogic === 'undefined') {
      alert('Error: BillingEngineLogic is not loaded. Please refresh the page.');
      console.error('BillingEngineLogic is undefined');
      return;
    }
    
    // Debug: Check if add_period method exists
    if (typeof BillingEngineLogic.add_period !== 'function') {
      alert('Error: BillingEngineLogic.add_period is not a function. Please refresh the page.');
      console.error('BillingEngineLogic.add_period is not a function');
      return;
    }
    
    // Call the add_period function
    BillingEngineLogic.add_period();
    
    // Get the updated periods to confirm it was added
    const periods = BillingEngineLogic.getPeriods();
    console.log('Period added. Total periods:', periods.length);
    
    BillingEngineUI.save_revision('Period Added', `Period ${periods.length} added`);
    
    // Render the UI
    if(BillingEngineUI.render) {
      BillingEngineUI.render();
    } else {
      render(); // Fallback to legacy function
    }
    
    console.log('Render completed. Periods in UI should be updated.');
  } catch (error) {
    const errorMessage = error.message || 'Unknown error occurred';
    alert('Error adding period: ' + errorMessage);
    console.error('Error in add_period:', error);
    console.error('Error stack:', error.stack);
    if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.log_error) {
      BillingEngineUI.log_error(errorMessage);
    }
  }
};

// Global function - must be accessible from HTML onclick
window.add_reading = function() {
  try {
    const active = BillingEngineLogic.getActive();
    if (active === null) {
      alert('No active period. Please create a period first by clicking "Add Period".');
      return;
    }
    
    BillingEngineLogic.add_reading();
    BillingEngineUI.save_revision('Reading Added', `Period ${BillingEngineLogic.getActive() + 1}: New reading row added`);
    if(BillingEngineUI.render) {
      BillingEngineUI.render();
    } else {
      render(); // Fallback to legacy function
    }
  } catch (error) {
    const errorMessage = error.message || 'Unknown error occurred';
    alert('Error adding reading: ' + errorMessage);
    if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.log_error) {
      BillingEngineUI.log_error(errorMessage);
    }
    console.error('Error in add_reading:', error);
  }
};

async function calculate() {
  try {
    // MANDATORY: Check if template is selected before calculation
    if (typeof currentTemplateTiers === 'undefined' || currentTemplateTiers === null || currentTemplateTiers.length === 0) {
      const errorMsg = "Please select a tariff template before calculating. A template is required for cost calculations.";
      BillingEngineUI.log_error(errorMsg);
      // Show error message in UI
      const errorDiv = document.getElementById('calculate_error');
      if (errorDiv) {
        errorDiv.textContent = errorMsg;
        errorDiv.style.display = 'block';
        errorDiv.style.color = 'var(--red, #dc2626)'; // Red for actual error
        // Hide error after 5 seconds
        setTimeout(() => {
          errorDiv.style.display = 'none';
        }, 5000);
      }
      return; // Stop calculation - template is mandatory
    }
    
    // Clear any previous error message
    const errorDiv = document.getElementById('calculate_error');
    if (errorDiv) {
      errorDiv.style.display = 'none';
      errorDiv.textContent = '';
    }
    
    // VALIDATION: Check for invalid readings before calculation
    // This prevents negative usage by validating reading monotonicity
    const periods = BillingEngineLogic.getPeriods();
    const all_readings = [];
    periods.forEach((p, period_idx) => {
      p.readings
        .filter(r => r.date && r.value !== null)
        .forEach(r => {
          all_readings.push({
            date: new Date(r.date),
            value: r.value,
            period_index: period_idx
          });
        });
    });
    
    // Sort all readings by date
    all_readings.sort((a, b) => a.date - b.date);
    
    // Validate reading monotonicity - each reading must be > previous reading
    for (let i = 1; i < all_readings.length; i++) {
      const current = all_readings[i];
      const previous = all_readings[i - 1];
      
      if (current.value <= previous.value) {
        const prevDateStr = current.date.toISOString().slice(0, 10);
        const currDateStr = previous.date.toISOString().slice(0, 10);
        const errorMsg = `Reading value (${current.value} L on ${prevDateStr}) cannot be lower than or equal to a previous reading (${previous.value} L on ${currDateStr}). Please enter a value greater than ${previous.value} L.`;
        
        BillingEngineUI.log_error(errorMsg);
        if (errorDiv) {
          errorDiv.textContent = errorMsg;
          errorDiv.style.display = 'block';
          errorDiv.style.color = 'var(--red, #dc2626)';
        }
        throw new Error(errorMsg);
      }
    }
    
    // Validate period opening constraint - first reading in period must be > period.opening
    for (let period_idx = 1; period_idx < periods.length; period_idx++) {
      const p = periods[period_idx];
      const prev_period = periods[period_idx - 1];
      
      if (prev_period && prev_period.closing !== null && prev_period.closing !== undefined) {
        const period_readings = p.readings
          .filter(r => r.date && r.value !== null)
          .map(r => ({ date: new Date(r.date), value: r.value }))
          .sort((a, b) => a.date - b.date);
        
        if (period_readings.length > 0) {
          const first_reading = period_readings[0];
          
          if (first_reading.value <= prev_period.closing) {
            const firstDateStr = first_reading.date.toISOString().slice(0, 10);
            const errorMsg = `Reading value (${first_reading.value} L on ${firstDateStr}) cannot be lower than or equal to the period opening reading (${prev_period.closing} L). This period opens at ${prev_period.closing} L (from previous period's closing reading). Please enter a value greater than ${prev_period.closing} L.`;
            
            BillingEngineUI.log_error(errorMsg);
            if (errorDiv) {
              errorDiv.textContent = errorMsg;
              errorDiv.style.display = 'block';
              errorDiv.style.color = 'var(--red, #dc2626)';
            }
            throw new Error(errorMsg);
          }
        }
      }
    }
    
    // All validations passed - proceed with calculation
    // ENGINE SWITCH: Check selected engine
    const selectedEngine = typeof window.getCalculatorEngine === 'function' 
      ? window.getCalculatorEngine() 
      : 'js';
    
    if (selectedEngine === 'php') {
      // PHP ENGINE: Send payload to PHP endpoint
      await calculateWithPhpEngine();
    } else {
      // JS ENGINE: Run existing JavaScript calculator logic
      BillingEngineLogic.calculate();
      if (typeof window.updateBillPreview === 'function') {
        window.updateBillPreview();
      }
      BillingEngineUI.save_revision('Calculation Performed', 'All periods recalculated');
      if(BillingEngineUI.render) {
        BillingEngineUI.render();
      } else {
        render(); // Fallback to legacy function
      }
      if(BillingEngineLogic.getActive() !== null){
        if(BillingEngineUI.render_calculation_output) {
          BillingEngineUI.render_calculation_output();
        } else {
          render_calculation_output(); // Fallback to legacy function
        }
      }
    }
  } catch (error) {
    BillingEngineUI.log_error(error.message);
    console.error("Error in calculate:", error);
  }
}

/**
 * Calculate using PHP engine
 * Sends payload to PHP endpoint and receives output in identical schema
 */
async function calculateWithPhpEngine() {
  try {
    // Collect all calculator inputs (matching JS structure)
    const periods = BillingEngineLogic.getPeriods();
    const billDay = parseInt(document.getElementById('bill_day')?.value || 15);
    const startMonth = document.getElementById('start_month')?.value || '';
    
    // Get tiers
    const tiers = typeof currentTemplateTiers !== 'undefined' && currentTemplateTiers !== null && currentTemplateTiers.length > 0
      ? currentTemplateTiers
      : [];
    
    // Prepare payload (identical to what JS uses internally)
    const payload = {
      bill_day: billDay,
      start_month: startMonth,
      tiers: tiers,
      periods: periods.map((p, idx) => ({
        index: idx,
        start: p.start ? new Date(p.start).toISOString().slice(0, 10) : null,
        end: p.end ? new Date(p.end).toISOString().slice(0, 10) : null,
        status: p.status || 'PROVISIONAL',
        readings: (p.readings || []).map(r => ({
          date: r.date ? (typeof r.date === 'string' ? r.date : new Date(r.date).toISOString().slice(0, 10)) : null,
          value: r.value !== null && r.value !== undefined ? parseFloat(r.value) : null
        })).filter(r => r.date && r.value !== null),
        opening: p.opening !== null && p.opening !== undefined ? parseFloat(p.opening) : null,
        closing: p.closing !== null && p.closing !== undefined ? parseFloat(p.closing) : null,
        usage: p.usage !== null && p.usage !== undefined ? parseFloat(p.usage) : null,
        dailyUsage: p.dailyUsage !== null && p.dailyUsage !== undefined ? parseFloat(p.dailyUsage) : null,
        start_reading: p.start_reading !== null && p.start_reading !== undefined ? parseFloat(p.start_reading) : null,
        original_provisional_usage: p.original_provisional_usage !== null && p.original_provisional_usage !== undefined ? parseFloat(p.original_provisional_usage) : null
      }))
    };
    
    // Disable calculate button during request
    const calculateBtn = document.getElementById('calculate_btn');
    if (calculateBtn) {
      calculateBtn.disabled = true;
      calculateBtn.textContent = 'Calculating...';
    }
    
    // Send to PHP endpoint
    const response = await fetch('/admin/billing-calculator/php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify(payload)
    });
    
    if (!response.ok) {
      throw new Error(`PHP calculator error: ${response.status} ${response.statusText}`);
    }
    
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.message || 'PHP calculator returned an error');
    }
    
    // Convert PHP output back to JS structure
    const phpPeriods = result.data.periods || [];
    const jsPeriods = phpPeriods.map(p => {
      const jsPeriod = {
        start: p.start ? new Date(p.start) : null,
        end: p.end ? new Date(p.end) : null,
        status: p.status || 'PROVISIONAL',
        readings: (p.readings || []).map(r => ({
          date: r.date ? new Date(r.date) : null,
          value: r.value !== null ? parseFloat(r.value) : null
        })),
        opening: p.opening !== null ? parseFloat(p.opening) : null,
        closing: p.closing !== null ? parseFloat(p.closing) : null,
        usage: p.usage !== null ? parseFloat(p.usage) : null,
        dailyUsage: p.dailyUsage !== null ? parseFloat(p.dailyUsage) : null,
        start_reading: p.start_reading !== null ? parseFloat(p.start_reading) : null,
        original_provisional_usage: p.original_provisional_usage !== null ? parseFloat(p.original_provisional_usage) : null,
        sectors: p.sectors || [],
        reconciliation: p.reconciliation || null,
        reconciliation_metadata: p.reconciliation_metadata || null,
        adjustment_brought_forward: p.adjustment_brought_forward !== null && p.adjustment_brought_forward !== undefined ? parseFloat(p.adjustment_brought_forward) : null,
        reconciliation_from_period: p.reconciliation_from_period !== null && p.reconciliation_from_period !== undefined ? parseInt(p.reconciliation_from_period) : null
      };
      return jsPeriod;
    });
    
    // Populate BillingEngineLogic state
    BillingEngineLogic.setPeriods(jsPeriods);
    
    // Update active period
    if (jsPeriods.length > 0) {
      BillingEngineLogic.setActive(jsPeriods.length - 1);
    }
    
    // Use existing render functions (JavaScript acts as renderer only)
    if (typeof window.updateBillPreview === 'function') {
      window.updateBillPreview();
    }
    BillingEngineUI.save_revision('Calculation Performed (PHP Engine)', 'All periods recalculated using PHP engine');
    if(BillingEngineUI.render) {
      BillingEngineUI.render();
    } else {
      render(); // Fallback to legacy function
    }
    if(BillingEngineLogic.getActive() !== null){
      if(BillingEngineUI.render_calculation_output) {
        BillingEngineUI.render_calculation_output();
      } else {
        render_calculation_output(); // Fallback to legacy function
      }
    }
    
  } catch (error) {
    BillingEngineUI.log_error(error.message || 'PHP calculator error');
    console.error("Error in calculateWithPhpEngine:", error);
    const errorDiv = document.getElementById('calculate_error');
    if (errorDiv) {
      errorDiv.textContent = error.message || 'PHP calculator error';
      errorDiv.style.display = 'block';
      errorDiv.style.color = 'var(--red, #dc2626)';
    }
  } finally {
    // Re-enable calculate button
    const calculateBtn = document.getElementById('calculate_btn');
    if (calculateBtn) {
      calculateBtn.disabled = false;
      calculateBtn.textContent = 'Calculate';
    }
  }
}

/**
 * Get the currently selected calculator engine
 * @returns {string} 'js' or 'php'
 */
window.getCalculatorEngine = function() {
  const engineSelect = document.getElementById('calculator_engine');
  if (engineSelect) {
    return engineSelect.value || 'js';
  }
  return 'js'; // Default to JS if selector not found
};

/**
 * Handle engine selector change
 * Shows warning and updates indicator
 */
window.onEngineChange = function() {
  const engineSelect = document.getElementById('calculator_engine');
  const warningDiv = document.getElementById('engine_warning');
  const statusSpan = document.getElementById('engine_status');
  
  if (!engineSelect) {
    return;
  }
  
  const selectedEngine = engineSelect.value || 'js';
  const engineName = selectedEngine === 'php' ? 'PHP (Experimental)' : 'JavaScript (Legacy)';
  
  // Update status indicator
  if (statusSpan) {
    statusSpan.textContent = engineName;
    statusSpan.style.color = selectedEngine === 'php' ? '#f59e0b' : '#10b981';
  }
  
  // Show warning that results will be cleared
  if (warningDiv) {
    warningDiv.style.display = 'block';
  }
  
  console.log('Calculator engine changed to:', engineName);
};

/**
 * Run comparison test between JavaScript and PHP calculators
 * Captures current JS output and compares with PHP output
 */
window.runComparisonTest = async function() {
  const testBtn = document.getElementById('comparison_test_btn');
  const resultDiv = document.getElementById('comparison_test_result');
  
  try {
    // Disable button and show loading
    if (testBtn) {
      testBtn.disabled = true;
      testBtn.textContent = '🔄 Running Comparison Test...';
    }
    
    if (resultDiv) {
      resultDiv.style.display = 'block';
      resultDiv.innerHTML = '<div style="padding:12px; background:#e7f3ff; border:1px solid #b3d9ff; border-radius:6px; color:#004085;">Running comparison test...</div>';
    }
    
    console.log('Running comparison test...');
    
    // Get current calculator state
    const periods = BillingEngineLogic.getPeriods();
    const billDay = parseInt(document.getElementById('bill_day')?.value || 15);
    const startMonth = document.getElementById('start_month')?.value || '';
    const tiers = typeof currentTemplateTiers !== 'undefined' && currentTemplateTiers !== null && currentTemplateTiers.length > 0
      ? currentTemplateTiers
      : [];
    
    if (periods.length === 0) {
      if (resultDiv) {
        resultDiv.innerHTML = '<div style="padding:12px; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; color:#856404;">⚠️ Please calculate periods first before running comparison test.</div>';
      }
      if (testBtn) {
        testBtn.disabled = false;
        testBtn.textContent = '🔬 Run Comparison Test (JS vs PHP)';
      }
      return;
    }
    
    // Prepare inputs
    const allReadings = [];
    periods.forEach((p, periodIdx) => {
      (p.readings || []).forEach(r => {
        if (r.date && r.value !== null) {
          allReadings.push({
            period_index: periodIdx,
            date: typeof r.date === 'string' ? r.date : new Date(r.date).toISOString().slice(0, 10),
            value: parseFloat(r.value)
          });
        }
      });
    });
    
    const inputs = {
      bill_day: billDay,
      start_month: startMonth,
      tiers: tiers,
      period_count: periods.length,
      readings: allReadings
    };
    
    // Format JS output
    const jsOutput = {
      periods: periods.map((p, idx) => ({
        index: idx,
        start: p.start ? (typeof p.start === 'string' ? p.start : new Date(p.start).toISOString().slice(0, 10)) : null,
        end: p.end ? (typeof p.end === 'string' ? p.end : new Date(p.end).toISOString().slice(0, 10)) : null,
        status: p.status || 'PROVISIONAL',
        opening: p.opening !== null && p.opening !== undefined ? parseFloat(p.opening) : null,
        closing: p.closing !== null && p.closing !== undefined ? parseFloat(p.closing) : null,
        usage: p.usage !== null && p.usage !== undefined ? parseFloat(p.usage) : null,
        dailyUsage: p.dailyUsage !== null && p.dailyUsage !== undefined ? parseFloat(p.dailyUsage) : null,
        start_reading: p.start_reading !== null && p.start_reading !== undefined ? parseFloat(p.start_reading) : null,
        original_provisional_usage: p.original_provisional_usage !== null && p.original_provisional_usage !== undefined ? parseFloat(p.original_provisional_usage) : null,
        sectors: (p.sectors || []).map(s => ({
          sector_id: s.sector_id ?? null,
          sub_id: s.sub_id ?? null,
          start_date: s.start_date ? (typeof s.start_date === 'string' ? s.start_date : new Date(s.start_date).toISOString().slice(0, 10)) : null,
          end_date: s.end_date ? (typeof s.end_date === 'string' ? s.end_date : new Date(s.end_date).toISOString().slice(0, 10)) : null,
          start_reading: s.start_reading !== null && s.start_reading !== undefined ? parseFloat(s.start_reading) : null,
          end_reading: s.end_reading !== null && s.end_reading !== undefined ? parseFloat(s.end_reading) : null,
          total_usage: s.total_usage !== null && s.total_usage !== undefined ? parseFloat(s.total_usage) : (s.usage_in_period !== null && s.usage_in_period !== undefined ? parseFloat(s.usage_in_period) : null),
          days_in_period: s.days_in_period !== null && s.days_in_period !== undefined ? parseInt(s.days_in_period) : null,
          usage_in_period: s.usage_in_period !== null && s.usage_in_period !== undefined ? parseFloat(s.usage_in_period) : (s.total_usage !== null && s.total_usage !== undefined ? parseFloat(s.total_usage) : null),
          daily_usage: s.daily_usage !== null && s.daily_usage !== undefined ? parseFloat(s.daily_usage) : null
        })),
        readings: (p.readings || []).map(r => ({
          date: r.date ? (typeof r.date === 'string' ? r.date : new Date(r.date).toISOString().slice(0, 10)) : null,
          value: r.value !== null && r.value !== undefined ? parseFloat(r.value) : null
        })).filter(r => r.date && r.value !== null),
        reconciliation: p.reconciliation || null,
        reconciliation_metadata: p.reconciliation_metadata || null,
        adjustment_brought_forward: p.adjustment_brought_forward !== null && p.adjustment_brought_forward !== undefined ? parseFloat(p.adjustment_brought_forward) : null,
        reconciliation_from_period: p.reconciliation_from_period !== null && p.reconciliation_from_period !== undefined ? parseInt(p.reconciliation_from_period) : null
      }))
    };
    
    // Send to dual-run endpoint
    const response = await fetch('/admin/billing-calculator/api/dual-run-test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        test_case_id: 'browser-comparison-' + Date.now(),
        inputs: inputs,
        js_output: jsOutput,
        context: {
          bill_day: billDay,
          start_month: startMonth,
          period_count: periods.length
        }
      })
    });
    
    if (!response.ok) {
      throw new Error(`Comparison test error: ${response.status} ${response.statusText}`);
    }
    
    const result = await response.json();
    
    // Display results in UI
    if (resultDiv) {
      resultDiv.style.display = 'block';
      
      if (result.parity_status === 'PASS') {
        resultDiv.innerHTML = `
          <div style="padding:12px; background:#d4edda; border:1px solid #c3e6cb; border-radius:6px; color:#155724;">
            <div style="font-weight:600; margin-bottom:8px;">✅ Comparison Test PASSED</div>
            <div style="font-size:12px;">All fields match between JavaScript and PHP calculators.</div>
            <div style="margin-top:8px; font-size:11px; color:#6c757d;">Test ID: ${result.test_case_id}</div>
          </div>
        `;
      } else {
        let diffsHtml = '';
        result.diffs.slice(0, 10).forEach((diff, idx) => {
          const jsVal = typeof diff.js_value === 'object' ? JSON.stringify(diff.js_value) : diff.js_value;
          const phpVal = typeof diff.php_value === 'object' ? JSON.stringify(diff.php_value) : diff.php_value;
          diffsHtml += `
            <div style="margin-top:8px; padding:8px; background:#fff3cd; border-left:3px solid #ffc107; border-radius:4px;">
              <div style="font-weight:600; font-size:12px;">${idx + 1}. ${diff.path}</div>
              <div style="font-size:11px; margin-top:4px;">
                <div>JS: <code style="background:#f8f9fa; padding:2px 4px; border-radius:2px;">${jsVal}</code></div>
                <div>PHP: <code style="background:#f8f9fa; padding:2px 4px; border-radius:2px;">${phpVal}</code></div>
              </div>
            </div>
          `;
        });
        
        resultDiv.innerHTML = `
          <div style="padding:12px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; color:#721c24;">
            <div style="font-weight:600; margin-bottom:8px;">⚠️ Comparison Test FAILED</div>
            <div style="font-size:12px; margin-bottom:12px;">Found ${result.diff_count} difference(s):</div>
            ${diffsHtml}
            ${result.diff_count > 10 ? `<div style="margin-top:8px; font-size:11px; color:#856404;">... and ${result.diff_count - 10} more differences (check console)</div>` : ''}
            <div style="margin-top:12px; font-size:11px; color:#6c757d;">Test ID: ${result.test_case_id}</div>
          </div>
        `;
      }
    }
    
    // Log full results to console
    console.log('Comparison Test Results:', result);
    console.log('Full Comparison Results:', JSON.stringify(result, null, 2));
    
    return result;
    
  } catch (error) {
    console.error('Error in comparison test:', error);
    
    if (resultDiv) {
      resultDiv.style.display = 'block';
      resultDiv.innerHTML = `
        <div style="padding:12px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; color:#721c24;">
          <div style="font-weight:600; margin-bottom:8px;">❌ Comparison Test Error</div>
          <div style="font-size:12px;">${error.message}</div>
        </div>
      `;
    }
    
    if (testBtn) {
      testBtn.disabled = false;
      testBtn.textContent = '🔬 Run Comparison Test (JS vs PHP)';
    }
    
    throw error;
  } finally {
    // Re-enable button if still in loading state
    if (testBtn && testBtn.textContent.includes('Running')) {
      testBtn.disabled = false;
      testBtn.textContent = '🔬 Run Comparison Test (JS vs PHP)';
    }
  }
};

// Render wrapper - delegates to UI container
function render() {
  if(BillingEngineUI.render && typeof BillingEngineUI.render === 'function') {
    BillingEngineUI.render();
  } else {
    // Fallback: call legacy render function (will be removed after full migration)
    const periods = BillingEngineLogic.getPeriods();
    const active = BillingEngineLogic.getActive();
    const pt = document.getElementById("period_table");
    if (!pt) {
      // Element not found - likely in Date to Date mode, skip Period to Period rendering
      return;
    }
    
    pt.innerHTML = "<tr><th>#</th><th>Billing Period</th><th>Status</th><th>Period_Total_Usage (L)</th></tr>";

    periods.forEach((p,i)=>{
      const tr = document.createElement("tr");
      if(i===active) tr.classList.add("ACTIVE");
      tr.onclick = ()=>{ 
        BillingEngineLogic.setActive(i); 
        render(); 
      };

      const period_display = BillingEngineLogic.format_period_display(p);
      const status_class = p.status === "ACTUAL" ? "ACTUAL" : 
                         p.status === "PROVISIONAL" ? "PROVISIONAL" : 
                         p.status === "CALCULATED" ? "CALCULATED" : "PROVISIONAL";
      
      let usage_display = "—";
      if(p.usage !== null && p.usage !== undefined){
        if(p.status === "CALCULATED" && p.original_provisional_usage !== null && p.original_provisional_usage !== undefined){
          usage_display = `${p.original_provisional_usage.toFixed(0)} (${p.usage.toFixed(0)})`;
        } else {
          usage_display = p.usage.toFixed(0);
        }
      }
      
      tr.innerHTML = `
        <td>${i+1}</td>
        <td>${period_display}</td>
        <td><span class="badge ${status_class}">${p.status || "PROVISIONAL"}</span></td>
        <td>${usage_display}</td>
      `;
      pt.appendChild(tr);
    });

    // Call legacy render_readings and render_calculation_output
    if(typeof render_readings === 'function') render_readings();
    if (periods[active] && periods[active].usage !== undefined) {
      if(typeof render_calculation_output === 'function') render_calculation_output();
    } else {
      if(typeof render_debug === 'function') render_debug();
    }
  }
}

// UI wrappers
function copy_output_to_clipboard() {
  BillingEngineUI.copy_output_to_clipboard();
}

function copy_all_periods_to_clipboard() {
  BillingEngineUI.copy_all_periods_to_clipboard();
}

function show_sector_analysis() {
  BillingEngineUI.show_sector_analysis();
}

function next_period_update() {
  BillingEngineUI.next_period_update();
}

function copy_input_history() {
  BillingEngineUI.copy_input_history();
}

function clear_revision_history() {
  BillingEngineUI.clear_revision_history();
}

function save_html_with_revisions() {
  BillingEngineUI.save_html_with_revisions();
}

// Expose periods and active for legacy code access
Object.defineProperty(window, 'periods', {
  get: function() { return BillingEngineLogic.getPeriods(); },
  set: function(val) { BillingEngineLogic.setPeriods(val); }
});

Object.defineProperty(window, 'active', {
  get: function() { return BillingEngineLogic.getActive(); },
  set: function(val) { BillingEngineLogic.setActive(val); }
});

// Expose helper functions for legacy code access
window.iso = function(d) { return BillingEngineLogic.iso(d); };
window.format_number = function(num) { return BillingEngineLogic.format_number(num); };
window.format_date = function(d) { return BillingEngineLogic.format_date(d); };
window.format_period_display = function(period) { return BillingEngineLogic.format_period_display(period); };
window.days_between = function(a, b) { return BillingEngineLogic.days_between(a, b); };
window.get_tiers = function() { return BillingEngineLogic.get_tiers(); };

/* ==================== MODE SWITCHING ==================== */
// Global billing mode state
let billingMode = 'period'; // 'period' or 'sector'

// Expose switchBillingMode on window for inline script and HTML onclick handlers
window.switchBillingMode = function(mode) {
  billingMode = mode;
  
  // Update tab buttons
  document.querySelectorAll('.mode-tab').forEach(tab => {
    if (tab.dataset.mode === mode) {
      tab.classList.add('active');
    } else {
      tab.classList.remove('active');
    }
  });
  
  // Show/hide containers (completely separate applications)
  const periodContainer = document.getElementById('period-mode-container');
  const sectorContainer = document.getElementById('sector-mode-container');
  
  if (periodContainer) {
    periodContainer.style.display = mode === 'period' ? '' : 'none';
    
    // Ensure dashboard is visible when period mode is active
    if (mode === 'period') {
      const dashboardEl = document.getElementById('period_dashboard');
      if (dashboardEl) {
        dashboardEl.style.display = 'block';
        // Update dashboard when switching to Period to Period mode
        if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.updatePeriodDashboard) {
          BillingEngineUI.updatePeriodDashboard();
        }
      }
      // Re-render Period to Period view when switching to it
      if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
        BillingEngineUI.render();
      }
    }
  }
  
  if (sectorContainer) {
    sectorContainer.style.display = mode === 'sector' ? '' : 'none';
    
    // Ensure dashboard is visible when sector mode is active
    if (mode === 'sector') {
      const dashboardEl = document.getElementById('sector_dashboard');
      if (dashboardEl) {
        dashboardEl.style.display = 'block';
        if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDashboard) {
          SectorBillingUI.updateDashboard();
        }
      }
    }
  }
  
  // Update template dropdowns based on new mode (check if function exists)
  if (typeof updateTemplateDropdowns === 'function') {
    updateTemplateDropdowns();
  }
  
  // Update Calculate button state after mode switch
  // (Template may be cleared if it doesn't match the new mode)
  if (typeof updateCalculateButtonState === 'function') {
    updateCalculateButtonState();
  }
  
  // Clear selected template if it doesn't match the new mode (check if variable exists)
  if (typeof currentTariffTemplate !== 'undefined' && currentTariffTemplate) {
    const templateBillingType = currentTariffTemplate.billing_type || 'MONTHLY';
    const requiredBillingType = mode === 'period' ? 'MONTHLY' : 'DATE_TO_DATE';
    
    if (templateBillingType !== requiredBillingType) {
      // Template doesn't match mode - clear it
      if (typeof window.clearTariffTemplate === 'function') {
        window.clearTariffTemplate(mode);
      }
      const periodDropdown = document.getElementById('tariff_template_select');
      const sectorDropdown = document.getElementById('sector_tariff_template_select');
      if (periodDropdown) periodDropdown.value = '';
      if (sectorDropdown) sectorDropdown.value = '';
      if (typeof window.showTariffError === 'function') {
        window.showTariffError('Please select a tariff template for ' + (mode === 'period' ? 'Period to Period' : 'Date to Date') + ' billing mode.', mode);
      }
    }
  }
  
  // Re-render appropriate UI
  if (mode === 'period') {
    if (typeof BillingEngineUI !== 'undefined' && BillingEngineUI.render) {
      BillingEngineUI.render();
    }
  } else {
    if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.render) {
      SectorBillingUI.render();
    }
    
    // Ensure dashboard is visible when sector mode is active
    const dashboardEl = document.getElementById('sector_dashboard');
    if (dashboardEl) {
      dashboardEl.style.display = 'block';
      if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.updateDashboard) {
        SectorBillingUI.updateDashboard();
      }
    }
  }
}

/* ==================== SECTOR BILLING LOGIC ==================== */
// @PROTECTED_MODULE: SectorBillingLogic
// This container encapsulates ALL sector billing calculation logic
// CRITICAL: All sector logic functions, state, and calculations are contained here
// This module operates independently from BillingEngineLogic

const SectorBillingLogic = (function() {
  'use strict';
  
  // ==================== PRIVATE STATE ====================
  let sectors = [];
  let active_sector = null;
  let sector_tiers = [
    { max: 6000, rate: 50 },
    { max: 15000, rate: 70 },
    { max: 45000, rate: 90 },
    { max: 100000, rate: 120 },
    { max: Infinity, rate: 120 }
  ];
  let selected_date = null;
  let current_year = new Date().getFullYear();
  let selected_month = new Date().getMonth();
  let current_date = new Date(); // Track the "current date" (date selected by user when adding reading)
  
  // ==================== PRIVATE HELPER FUNCTIONS ====================
  
  function iso(d) {
    if (!d) return '';
    const date = d instanceof Date ? d : new Date(d);
    return date.toISOString().slice(0, 10);
  }
  
  function format_number(num) {
    if (num === null || num === undefined) return '—';
    return Number(num).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
  }
  
  function format_date(d) {
    if (!d) return '—';
    const date = d instanceof Date ? d : new Date(d);
    const day = date.getDate();
    const suffix =
      day % 10 === 1 && day !== 11 ? "st" :
      day % 10 === 2 && day !== 12 ? "nd" :
      day % 10 === 3 && day !== 13 ? "rd" : "th";
    return `${day}${suffix} ${date.toLocaleString("en-GB", { month: "short" })} ${date.getFullYear()}`;
  }
  
  // Canonical Day Function: Days_Between (inclusive-inclusive)
  // Examples: Days_Between(20 Jan, 20 Jan) = 1, Days_Between(20 Jan, 30 Jan) = 11
  // Aligned with Period to Period mode
  function days_between(a, b) {
    const dateA = a instanceof Date ? a : new Date(a);
    const dateB = b instanceof Date ? b : new Date(b);
    dateA.setHours(12, 0, 0, 0);
    dateB.setHours(12, 0, 0, 0);
    // Add 1 for inclusive-inclusive (same as Period to Period mode)
    return Math.floor((dateB - dateA) / 86400000) + 1;
  }
  
  function format_sector_display(sector) {
    if (!sector || !sector.start_date) return '—';
    const start = format_date(sector.start_date);
    
    // For OPEN sectors, show "→ OPEN" or "→ No end date"
    if (sector.status === 'OPEN' || !sector.end_date) {
      return `${start} → OPEN`;
    }
    
    // For CLOSED sectors, show the full date range
    const end = format_date(sector.end_date);
    return `${start} → ${end}`;
  }
  
  function calculate_tier_cost(litres, tiers) {
    if (!tiers || tiers.length === 0) return { total: 0, items: [] };
    
    let remaining = litres;
    let prev = 0;
    let total = 0;
    const items = [];
    
    for (const t of tiers) {
      const cap = t.max - prev;
      const used = Math.max(0, Math.min(remaining, cap));
      if (used > 0) {
        const cost = (used / 1000) * t.rate;
        items.push({ prev, max: t.max, used, rate: t.rate, cost });
        total += cost;
        remaining -= used;
      }
      prev = t.max;
    }
    
    return { total, items };
  }
  
  // ==================== PUBLIC API ====================
  
  return {
    getSectors: function() { return sectors; },
    setSectors: function(s) { sectors = s; },
    getActiveSector: function() { return active_sector; },
    setActiveSector: function(idx) { active_sector = idx; },
    getSectorTiers: function() { return sector_tiers; },
    setSectorTiers: function(tiers) { sector_tiers = tiers; },
    getSelectedDate: function() { return selected_date; },
    setSelectedDate: function(date) { selected_date = date; },
    getCurrentYear: function() { return current_year; },
    setCurrentYear: function(year) { current_year = year; },
    getSelectedMonth: function() { return selected_month; },
    setSelectedMonth: function(month) { selected_month = month; },
    getCurrentDate: function() { return current_date; },
    setCurrentDate: function(date) { 
      if (date) {
        current_date = new Date(date);
        current_date.setHours(12, 0, 0, 0);
      }
    },
    
    addReading: function(date, value) {
      if (!date || value === null || value === undefined) {
        throw new Error('Date and value are required');
      }
      
      const readingDate = new Date(date);
      readingDate.setHours(12, 0, 0, 0);
      
      // If no sectors exist, create first sector
      if (sectors.length === 0) {
        sectors.push({
          sector_id: 1,
          start_date: new Date(readingDate),
          end_date: null, // No end date for OPEN sector
          start_reading: value,
          end_reading: value,
          total_usage: 0,
          daily_usage: 0,
          days: 0,
          status: 'OPEN',
          readings: [{ date: iso(readingDate), value: value }],
          tier_cost: 0,
          tier_items: []
        });
        active_sector = 0;
        return;
      }
      
      // Get active sector
      const currentSector = sectors[active_sector !== null ? active_sector : sectors.length - 1];
      
      // REMOVED: Auto-closing logic when >30 days
      // Periods should remain OPEN until manually closed via CLOSE button
      // The CLOSE button will appear when >30 days, allowing user to manually close the period
      // When user clicks CLOSE button, closeSector() function will handle closing and creating new sector
      
      // Add reading to current sector
      currentSector.readings.push({ date: iso(readingDate), value: value });
      
      // Update sector totals
      if (currentSector.readings.length >= 2) {
        const prevReading = currentSector.readings[currentSector.readings.length - 2];
        const prevDate = new Date(prevReading.date);
        prevDate.setHours(12, 0, 0, 0);
        const daysBetween = days_between(prevDate, readingDate);
        
        if (daysBetween > 0) {
          const usage = value - prevReading.value;
          currentSector.daily_usage = usage / daysBetween;
        }
      }
      
      // Update sector end reading (but keep end_date as null for OPEN sectors)
      currentSector.end_reading = value;
      currentSector.total_usage = value - currentSector.start_reading;
      
      // For OPEN sectors, calculate days from start to current reading
      if (currentSector.status === 'OPEN') {
        currentSector.days = days_between(currentSector.start_date, readingDate);
        if (currentSector.days > 0) {
          currentSector.daily_usage = currentSector.total_usage / currentSector.days;
        }
      }
      
      // Recalculate tier cost after updating usage
      const sectorIndex = sectors.indexOf(currentSector);
      if (sectorIndex >= 0) {
        const tierResult = calculate_tier_cost(currentSector.total_usage, sector_tiers);
        currentSector.tier_cost = tierResult.total;
        currentSector.tier_items = tierResult.items;
      }
    },
    
    calculateSector: function(sectorIndex) {
      if (sectorIndex < 0 || sectorIndex >= sectors.length) return;
      
      const sector = sectors[sectorIndex];
      
      // Calculate tier costs
      const tierResult = calculate_tier_cost(sector.total_usage, sector_tiers);
      sector.tier_cost = tierResult.total;
      sector.tier_items = tierResult.items;
    },
    
    // Manually close a sector/period (no longer automatic)
    // This function closes a sector at its last reading date
    closeSector: function(sectorIndex) {
      if (sectorIndex < 0 || sectorIndex >= sectors.length) return false;
      
      const sector = sectors[sectorIndex];
      if (sector.status !== 'OPEN') return false; // Already closed
      
      // Get all readings with BOTH date AND value (complete readings only)
      const readingsWithDatesAndValues = sector.readings.filter(r => 
        r.date && r.value !== null && r.value !== undefined
      );
      if (readingsWithDatesAndValues.length === 0) return false; // Need at least one reading
      
      const sortedReadings = [...readingsWithDatesAndValues].sort((a, b) => new Date(a.date) - new Date(b.date));
      const lastReading = sortedReadings[sortedReadings.length - 1];
      
      if (!lastReading.date || lastReading.value === null || lastReading.value === undefined) return false;
      
      const closingDate = new Date(lastReading.date);
      closingDate.setHours(12, 0, 0, 0);
      
      // Close this sector at the last reading date
      sector.status = 'CLOSED';
      sector.end_date = new Date(closingDate);
      sector.end_reading = lastReading.value;
      
      // Recalculate totals based on all readings
      // For Period 1: first reading value becomes start_reading
      // For Period 2+: start_reading is already set from previous period's end_reading
      if (sortedReadings.length > 0) {
        if (sector.start_reading === 0 || sector.start_reading === null || sector.start_reading === undefined) {
          // Period 1: first reading value becomes the start_reading
          sector.start_reading = sortedReadings[0].value;
        }
        // For Period 2+, start_reading is already set correctly from previous period
        
        sector.end_reading = lastReading.value;
        sector.total_usage = sector.end_reading - sector.start_reading;
        sector.days = SectorBillingLogic.daysBetween(sector.start_date, sector.end_date);
        if (sector.days > 0) {
          sector.daily_usage = sector.total_usage / sector.days;
        } else {
          sector.daily_usage = 0;
        }
      }
      
      // Calculate tier costs
      const tierResult = calculate_tier_cost(sector.total_usage, sector_tiers);
      sector.tier_cost = tierResult.total;
      sector.tier_items = tierResult.items;
      
      // Create new sector starting from closing date + 1 day with continuity reading
      const newSectorId = sectors.length + 1;
      const newSectorStartDate = new Date(closingDate);
      newSectorStartDate.setDate(newSectorStartDate.getDate() + 1); // Day after closing date
      newSectorStartDate.setHours(12, 0, 0, 0);
      
      // End reading from closed sector becomes start reading for new sector
      const previousEndReading = sector.end_reading !== null && sector.end_reading !== undefined 
        ? sector.end_reading 
        : 0;
      
      // Pre-populate first reading with closing date + 1 and previous end reading
      const firstReadingDate = SectorBillingLogic.iso(newSectorStartDate);
      
      sectors.push({
        sector_id: newSectorId,
        start_date: newSectorStartDate,
        end_date: null, // OPEN sector
        start_reading: previousEndReading, // Continuity from previous period
        end_reading: previousEndReading, // Initially set to opening reading for continuity
        total_usage: 0,
        daily_usage: 0,
        days: 0,
        status: 'OPEN',
        readings: [{ date: firstReadingDate, value: previousEndReading }], // Pre-populate with opening reading from previous period
        tier_cost: 0,
        tier_items: []
      });
      
      // Set the newly created sector as active so user can continue adding readings
      // This allows seamless continuation after closing a period
      active_sector = sectors.length - 1;
      
      return true;
    },
    
    // Check if a period should show the close button (> 30 days from start)
    shouldShowCloseButton: function(sectorIndex) {
      if (sectorIndex < 0 || sectorIndex >= sectors.length) return false;
      
      const sector = sectors[sectorIndex];
      if (sector.status !== 'OPEN') return false; // Already closed
      
      // Check if period has at least one reading with a value
      const readingsWithValues = sector.readings && sector.readings.filter(r => 
        r.date && r.value !== null && r.value !== undefined
      );
      if (!readingsWithValues || readingsWithValues.length === 0) return false; // No readings yet
      
      // Recalculate days and daily_usage to ensure they're current
      const sortedReadings = [...readingsWithValues].sort((a, b) => new Date(a.date) - new Date(b.date));
      const lastReading = sortedReadings[sortedReadings.length - 1];
      if (!lastReading || !lastReading.date) return false;
      
      const lastReadingDate = new Date(lastReading.date);
      lastReadingDate.setHours(12, 0, 0, 0);
      const periodStartDate = new Date(sector.start_date);
      periodStartDate.setHours(12, 0, 0, 0);
      
      // Calculate days between period start and last reading date (inclusive-inclusive)
      const daysSinceStart = this.daysBetween(periodStartDate, lastReadingDate);
      
      // Update sector.days to ensure it's current
      sector.days = daysSinceStart;
      
      // Show close button only if > 30 days from period start date
      return daysSinceStart > 30;
    },
    
    // Legacy function - kept for compatibility but no longer auto-closes
    checkAndCloseSectors: function() {
      // No longer automatically closes - periods remain open until manually closed
      return;
    },
    
    calculateTierCost: function(litres, tiers) {
      return calculate_tier_cost(litres, tiers || sector_tiers);
    },
    
    daysBetween: function(date1, date2) {
      return days_between(date1, date2);
    },
    
    formatSectorDisplay: function(sector) {
      return format_sector_display(sector);
    },
    
    formatNumber: function(num) {
      return format_number(num);
    },
    
    formatDate: function(d) {
      return format_date(d);
    },
    
    iso: function(d) {
      return iso(d);
    }
  };
})();

/* ==================== SECTOR BILLING UI ==================== */
// @PROTECTED_MODULE: SectorBillingUI
// This container encapsulates ALL sector billing UI rendering and interaction
// CRITICAL: All sector UI functions, rendering, and display logic are contained here
// This module operates independently from BillingEngineUI

const SectorBillingUI = (function() {
  'use strict';
  
  // ==================== PRIVATE STATE ====================
  let revisionNumber = 0;
  let revisions = [];
  
  // ==================== PRIVATE HELPER FUNCTIONS ====================
  
  function log_error(msg) {
    console.error('SectorBillingUI Error:', msg);
    const errorDiv = document.getElementById('sector_errors');
    if (errorDiv) {
      errorDiv.innerHTML = `<div class="error">${msg}</div>`;
    }
  }
  
  function getDaysInMonth(year, month) {
    return new Date(year, month + 1, 0).getDate();
  }
  
  // ==================== PUBLIC API ====================
  
  return {
    render: function() {
      this.renderSectorTable();
      this.renderSectorReadings();
      this.updateDashboard();
      if (SectorBillingLogic.getActiveSector() !== null) {
        this.renderSectorOutput();
      }
      this.updateAddReadingButton();
      // Update date picker default after rendering (to reflect latest reading)
      this.updateDatePickerDefault();
    },
    
    updateDashboard: function() {
      // Update dashboard
      const dashboardEl = document.getElementById('sector_dashboard');
      const active = SectorBillingLogic.getActiveSector();
      
      if (dashboardEl && active !== null) {
        // Make sure sector is calculated first
        SectorBillingLogic.calculateSector(active);
        // Get updated sectors after calculation
        const updatedSectors = SectorBillingLogic.getSectors();
        const sector = updatedSectors[active];
        
        if (sector) {
          // Calculate values
          const dailyUsage = sector.daily_usage || 0;
          const dailyCost = sector.days > 0 ? (sector.tier_cost || 0) / sector.days : 0;
          const currentCost = sector.tier_cost || 0;
          
          // Update dashboard elements
          const dailyUsageEl = document.getElementById('dashboard_daily_usage');
          const dailyCostEl = document.getElementById('dashboard_daily_cost');
          const usageDaysEl = document.getElementById('dashboard_usage_days');
          const totalUsedEl = document.getElementById('dashboard_total_used');
          const totalCostEl = document.getElementById('dashboard_total_cost');
          
          if (dailyUsageEl) dailyUsageEl.textContent = SectorBillingLogic.formatNumber(dailyUsage) + ' L';
          if (dailyCostEl) dailyCostEl.textContent = 'R' + dailyCost.toFixed(2);
          if (usageDaysEl) usageDaysEl.textContent = (sector.days || 0) + ' days';
          if (totalUsedEl) totalUsedEl.textContent = SectorBillingLogic.formatNumber(sector.total_usage || 0) + ' L';
          
          // Always show Total Cost (same as Current Cost)
          if (totalCostEl) {
            totalCostEl.textContent = 'R ' + currentCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
          }
          
          // Start Reading field removed - first reading value becomes start_reading
        }
      } else if (dashboardEl) {
        // No active sector - show dashes
        const dailyUsageEl = document.getElementById('dashboard_daily_usage');
        const dailyCostEl = document.getElementById('dashboard_daily_cost');
        const usageDaysEl = document.getElementById('dashboard_usage_days');
        const totalUsedEl = document.getElementById('dashboard_total_used');
        const totalCostEl = document.getElementById('dashboard_total_cost');
        
        if (dailyUsageEl) dailyUsageEl.textContent = '—';
        if (dailyCostEl) dailyCostEl.textContent = '—';
        if (usageDaysEl) usageDaysEl.textContent = '—';
        if (totalUsedEl) totalUsedEl.textContent = '—';
        if (totalCostEl) totalCostEl.textContent = 'R 0.00';
        
        // Start Reading field removed - no reset needed
      }
    },
    
    updateAddReadingButton: function() {
      const addButton = document.querySelector('button[onclick="add_sector_reading()"]');
      const closeButton = document.getElementById('close_period_button');
      
      if (!addButton) return;
      
      const active = SectorBillingLogic.getActiveSector();
      if (active === null) {
        addButton.disabled = false;
        addButton.style.opacity = '1';
        addButton.title = '';
        // Hide close button if no active sector
        if (closeButton) {
          closeButton.style.display = 'none';
        }
        return;
      }
      
      const sectors = SectorBillingLogic.getSectors();
      const sector = sectors[active];
      
      // Check if close button should be shown (> 30 days) - but don't disable Add Reading button
      // Date to Date mode should work like Period to Period - allow free input, auto-close when >30 days
      const shouldShowClose = sector && sector.status === 'OPEN' && SectorBillingLogic.shouldShowCloseButton(active);
      
      // Always enable Add Reading button (no restrictions - same as Period to Period mode)
      addButton.disabled = false;
      addButton.style.opacity = '1';
      addButton.style.cursor = 'pointer';
      addButton.title = '';
      
      // Show Close Period button if >30 days (optional manual close)
      if (shouldShowClose && closeButton) {
        closeButton.style.display = 'inline-block';
        closeButton.onclick = function() {
          SectorBillingLogic.closeSector(active);
          SectorBillingUI.render();
        };
      } else if (closeButton) {
        closeButton.style.display = 'none';
      }
    },
    
    renderMonthSelector: function() {
      // Update date picker default based on last reading (not current date)
      this.updateDatePickerDefault();
    },
    
    updateDatePickerDefault: function() {
      const datePicker = document.getElementById('sector_date_picker');
      if (!datePicker) return;
      
      const active = SectorBillingLogic.getActiveSector();
      const sectors = SectorBillingLogic.getSectors();
      
      // Check if any sector has a start_date - if so, disable the date picker
      let hasStartDate = false;
      let hasReadings = false;
      let sectorToCheck = null;
      
      // Prefer active sector, otherwise use first sector
      if (active !== null && sectors[active]) {
        sectorToCheck = sectors[active];
      } else if (sectors.length > 0) {
        sectorToCheck = sectors[0];
      }
      
      if (sectorToCheck && sectorToCheck.start_date) {
        hasStartDate = true;
        const defaultDate = SectorBillingLogic.iso(sectorToCheck.start_date);
        
        // Check if sector has readings with dates
        if (sectorToCheck.readings && sectorToCheck.readings.length > 0) {
          const readingsWithDates = sectorToCheck.readings.filter(r => r.date);
          hasReadings = readingsWithDates.length > 0;
        }
        
          // Only set initial value if no readings exist yet (initial state)
          if (hasReadings) {
            // Readings exist - ALWAYS clear value to let user manually select date
            datePicker.value = '';
          } else if (!datePicker.value) {
            // No readings yet - set initial value
            datePicker.value = defaultDate;
          }
        // DO NOT disable date picker - user needs to select dates for readings (matches old billing calculator logic)
        // Date picker remains enabled so user can select dates when adding readings
        // Set min to start date (will be updated later if readings exist)
        datePicker.setAttribute('min', defaultDate);
      }
      
      if (!hasStartDate) {
        // No start date set yet - enable date picker
        datePicker.disabled = false;
        datePicker.style.backgroundColor = '';
        datePicker.style.cursor = '';
        
        if (active === null) {
          // No active sector - use today
          const today = new Date();
          today.setHours(12, 0, 0, 0);
          if (!datePicker.value) {
            datePicker.value = SectorBillingLogic.iso(today);
          }
          datePicker.setAttribute('min', SectorBillingLogic.iso(today));
        } else {
          // Active sector exists but no start_date - use today
          const today = new Date();
          today.setHours(12, 0, 0, 0);
          if (!datePicker.value) {
            datePicker.value = SectorBillingLogic.iso(today);
          }
          datePicker.setAttribute('min', SectorBillingLogic.iso(today));
        }
        // Update formatted date display if function exists
        if (typeof window.updateFormattedDateDisplay === 'function') {
          window.updateFormattedDateDisplay();
        }
        return;
      }
      
      // Continue with active sector logic (sectors already declared above)
      const sector = sectors[active];
      if (!sector) return;
      
      // Get the last reading date (if any)
      let lastReadingDate = null;
      if (sector.readings && sector.readings.length > 0) {
        const readingsWithDates = sector.readings.filter(r => r.date);
        if (readingsWithDates.length > 0) {
          const sorted = [...readingsWithDates].sort((a, b) => new Date(a.date) - new Date(b.date));
          lastReadingDate = new Date(sorted[sorted.length - 1].date);
          lastReadingDate.setHours(12, 0, 0, 0);
        }
      }
      
      // Determine default date: day after last reading, or sector start date, or today (whichever is latest)
      let defaultDate = null;
      if (lastReadingDate) {
        // Default to day after last reading
        defaultDate = new Date(lastReadingDate);
        defaultDate.setDate(defaultDate.getDate() + 1);
        defaultDate.setHours(12, 0, 0, 0);
      } else if (sector.start_date) {
        // No readings yet - use sector start date
        defaultDate = new Date(sector.start_date);
        defaultDate.setHours(12, 0, 0, 0);
      } else {
        // Fallback to today
        defaultDate = new Date();
        defaultDate.setHours(12, 0, 0, 0);
      }
      
      // Only set default value when NO readings exist (initial state)
      // Once readings exist, leave value empty so user can manually select any date after last reading
      if (!lastReadingDate) {
        // No readings yet - set initial value to start date or today
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (!datePicker.value) {
          // Only set if not already set by user
          if (defaultDate >= today) {
            // Set default date (start date or today)
            datePicker.value = SectorBillingLogic.iso(defaultDate);
          } else {
            // Fallback to today if default would be in the past
            datePicker.value = SectorBillingLogic.iso(today);
          }
        }
      } else {
        // Readings exist - clear value to let user manually select date
        // The min attribute will restrict selection to dates after last reading
        datePicker.value = '';
      }
      
      // Set min attribute to prevent selecting dates earlier than last reading
      // BUT: Allow selecting dates >30 days from period start (will auto-close period)
      if (lastReadingDate) {
        const minDate = new Date(lastReadingDate);
        minDate.setDate(minDate.getDate() + 1); // Day after last reading
        datePicker.setAttribute('min', SectorBillingLogic.iso(minDate));
      } else if (sector.start_date) {
        // Only restrict to period start date if no readings exist yet
        // Once readings exist, allow any date >= last reading (auto-close will handle >30 days)
        datePicker.setAttribute('min', SectorBillingLogic.iso(sector.start_date));
      } else {
        datePicker.removeAttribute('min');
      }
      
      // Update formatted date display after date picker value is set
      if (typeof window.updateFormattedDateDisplay === 'function') {
        window.updateFormattedDateDisplay();
      }
    },
    
    renderSectorTable: function() {
      const container = document.getElementById('periods_list');
      if (!container) return;
      
      container.innerHTML = '';
      const sectors = SectorBillingLogic.getSectors();
      const active = SectorBillingLogic.getActiveSector();
      
      // Group sectors into periods (closed sectors = periods)
      const periods = [];
      let currentPeriod = null;
      
      sectors.forEach((sector, i) => {
        if (sector.status === 'CLOSED') {
          // Closed sector = a period
          // Store reference to original sector for selection (it's in main sectors array at index i)
          const originalSectorIndex = i;
          
          // Create sectors from consecutive reading pairs within this period
          // N readings → N-1 sectors
          const periodSectors = [];
          
          if (sector.readings && sector.readings.length > 0) {
            // Get all readings within this period (before period.end)
            const periodEndDate = new Date(sector.end_date);
            periodEndDate.setHours(0, 0, 0, 0);
            
            const readingsInPeriod = sector.readings.filter(r => {
              if (!r.date || r.value === null || r.value === undefined) return false;
              const rDate = new Date(r.date);
              rDate.setHours(12, 0, 0, 0);
              const periodEndDate = new Date(sector.end_date);
              periodEndDate.setHours(12, 0, 0, 0);
              // Include all readings on or before the closing date (the closing reading belongs to this period)
              return rDate <= periodEndDate;
            });
            
            if (readingsInPeriod.length > 1) {
              // Sort readings by date
              const sortedReadings = [...readingsInPeriod].sort((a, b) => new Date(a.date) - new Date(b.date));
              
              // Create sectors from consecutive reading pairs
              for (let j = 0; j < sortedReadings.length - 1; j++) {
                const earlier = sortedReadings[j];
                const later = sortedReadings[j + 1];
                
                const sectorStartDate = new Date(earlier.date);
                sectorStartDate.setHours(12, 0, 0, 0);
                const sectorEndDate = new Date(later.date);
                sectorEndDate.setHours(12, 0, 0, 0);
                
                // Calculate days between readings (inclusive-inclusive for sector)
                const sectorDays = SectorBillingLogic.daysBetween(sectorStartDate, sectorEndDate);
                const sectorUsage = later.value - earlier.value;
                const sectorDailyUsage = sectorDays > 0 ? sectorUsage / sectorDays : 0;
                
                // Create sector from this reading pair
                const readingSector = {
                  sector_id: periodSectors.length + 1,
                  start_date: sectorStartDate,
                  end_date: sectorEndDate,
                  start_reading: earlier.value,
                  end_reading: later.value,
                  total_usage: sectorUsage,
                  daily_usage: sectorDailyUsage,
                  days: sectorDays,
                  status: 'CLOSED',
                  readings: [earlier, later],
                  tier_cost: 0,
                  tier_items: []
                };
                
                // Tier costs will be calculated elsewhere (not part of sector materialization)
                // Set to 0 for now - will be calculated when needed
                readingSector.tier_cost = 0;
                readingSector.tier_items = [];
                
                periodSectors.push(readingSector);
              }
            } else if (readingsInPeriod.length === 1) {
              // Single reading in period - create a sector with that reading as both start and end
              const singleReading = readingsInPeriod[0];
              const readingDate = new Date(singleReading.date);
              readingDate.setHours(12, 0, 0, 0);
              
              const singleSector = {
                sector_id: 1,
                start_date: readingDate,
                end_date: readingDate,
                start_reading: singleReading.value,
                end_reading: singleReading.value,
                total_usage: 0,
                daily_usage: 0,
                days: 0,
                status: 'CLOSED',
                readings: [singleReading],
                tier_cost: 0,
                tier_items: []
              };
              
              periodSectors.push(singleSector);
            }
          }
          
          // If no sectors were created from readings, fall back to period itself as single sector
          // This should not happen in normal operation, but handle edge case
          if (periodSectors.length === 0) {
            periodSectors.push(sector);
          }
          
          // Calculate period totals from sectors
          const periodTotalUsage = periodSectors.reduce((sum, s) => sum + (s.total_usage || 0), 0);
          const periodStartDate = new Date(sector.start_date);
          periodStartDate.setHours(0, 0, 0, 0);
          const periodEndDate = new Date(sector.end_date);
          periodEndDate.setHours(0, 0, 0, 0);
          const periodDays = Math.floor((periodEndDate - periodStartDate) / 86400000); // Exclusive end, no +1
          const periodDailyUsage = periodDays > 0 ? periodTotalUsage / periodDays : 0;
          
          const periodObj = {
            period_id: periods.length + 1,
            start_date: sector.start_date,
            end_date: sector.end_date,
            status: 'CLOSED',
            total_usage: periodTotalUsage,
            daily_usage: periodDailyUsage,
            sectors: periodSectors, // Sectors created from reading pairs within this period
            originalSectorIndex: originalSectorIndex // Index of original sector in main sectors array (for selection)
          };
          periods.push(periodObj);
          currentPeriod = null;
        } else {
          // OPEN sector = current period
          if (!currentPeriod) {
            currentPeriod = {
              period_id: periods.length + 1,
              start_date: sector.start_date,
              end_date: null,
              status: 'OPEN',
              total_usage: sector.total_usage || 0,
              daily_usage: sector.daily_usage || 0,
              sectors: []
            };
            periods.push(currentPeriod);
          }
          currentPeriod.sectors.push(sector);
          // Update period totals from sector readings
          if (sector.readings && sector.readings.length > 0) {
            const readingsWithValues = sector.readings.filter(r => r.date && r.value !== null);
            if (readingsWithValues.length > 0) {
              const sorted = [...readingsWithValues].sort((a, b) => new Date(a.date) - new Date(b.date));
              const firstReading = sorted[0];
              const lastReading = sorted[sorted.length - 1];
              if (firstReading.value !== null && lastReading.value !== null) {
                const periodUsage = lastReading.value - firstReading.value;
                currentPeriod.total_usage = periodUsage;
                if (sector.start_date && lastReading.date) {
                  const startDate = new Date(sector.start_date);
                  startDate.setHours(12, 0, 0, 0);
                  const endDate = new Date(lastReading.date);
                  endDate.setHours(12, 0, 0, 0);
                  const periodDays = SectorBillingLogic.daysBetween(startDate, endDate);
                  if (periodDays > 0) {
                    currentPeriod.daily_usage = periodUsage / periodDays;
                  }
                }
              }
            }
          }
        }
      });
      
      periods.forEach((period, periodIdx) => {
        // Check if this period contains the active sector
        let containsActive = false;
        if (period.status === 'CLOSED' && period.originalSectorIndex !== undefined) {
          // For CLOSED periods, check if original sector index is active
          containsActive = (period.originalSectorIndex === active);
        } else {
          // For OPEN periods, check if any sector in period.sectors is active
          containsActive = period.sectors.some(s => sectors.indexOf(s) === active);
        }
        
        // Format period header: "1st Feb 2026 to 10 March 2026 - CLOSED" or "11 Mar 2026 > - OPEN"
        let periodHeader = '';
        if (period.end_date) {
          // CLOSED period: "1st Feb 2026 to 10 March 2026 - CLOSED"
          periodHeader = `${SectorBillingLogic.formatDate(period.start_date)} to ${SectorBillingLogic.formatDate(period.end_date)} - CLOSED`;
        } else {
          // OPEN period: "11 Mar 2026 > - OPEN"
          periodHeader = `${SectorBillingLogic.formatDate(period.start_date)} > - OPEN`;
        }
        
        // Add opening reading for all periods (Period 1 from start_reading, Period 2+ from previous period's end reading)
        let openingReading = null;
        if (periodIdx === 0) {
          // Period 1: Use start_reading from the first sector
          if (period.sectors.length > 0) {
            const firstSector = period.sectors[0];
            openingReading = firstSector?.start_reading !== null && firstSector?.start_reading !== undefined 
              ? firstSector.start_reading 
              : null;
          }
        } else {
          // Period 2+: Use end_reading from previous period
          const previousPeriod = periods[periodIdx - 1];
          if (previousPeriod && previousPeriod.status === 'CLOSED' && previousPeriod.sectors.length > 0) {
            const lastSector = previousPeriod.sectors[previousPeriod.sectors.length - 1];
            openingReading = lastSector?.end_reading !== null && lastSector?.end_reading !== undefined 
              ? lastSector.end_reading 
              : null;
          }
        }
        
        // Display opening reading in small font if available
        if (openingReading !== null && openingReading !== undefined && openingReading > 0) {
          periodHeader += ` <span style="font-size: 14px; font-weight: 400; color: #6b7280;">| Opening Reading: ${SectorBillingLogic.formatNumber(openingReading)} L</span>`;
        }
        
        // For CLOSED periods, calculate usage as closing_reading - opening_reading from actual sector
        // For OPEN periods, use period.total_usage calculated from sectors
        let usage = '—';
        let dailyUsage = '—';
        if (period.status === 'CLOSED' && period.originalSectorIndex !== undefined) {
          // Get the original sector
          const originalSector = sectors[period.originalSectorIndex];
          if (originalSector && originalSector.start_reading !== null && originalSector.start_reading !== undefined 
              && originalSector.end_reading !== null && originalSector.end_reading !== undefined) {
            // Calculate usage from actual readings: closing_reading - opening_reading
            const periodUsage = originalSector.end_reading - originalSector.start_reading;
            usage = SectorBillingLogic.formatNumber(periodUsage);
            
            // Calculate daily usage from period days
            if (originalSector.days > 0) {
              dailyUsage = SectorBillingLogic.formatNumber(periodUsage / originalSector.days);
            }
          }
        } else {
          // OPEN period: use period.total_usage calculated from sectors
          usage = period.total_usage !== null && period.total_usage !== undefined 
            ? SectorBillingLogic.formatNumber(period.total_usage) 
            : '—';
          dailyUsage = period.daily_usage !== null && period.daily_usage !== undefined 
            ? SectorBillingLogic.formatNumber(period.daily_usage) 
            : '—';
        }
        
        // Create period header div (bold, clickable)
        const periodDiv = document.createElement('div');
        periodDiv.className = 'period-header';
        periodDiv.style.cssText = `
          padding: 16px 20px;
          margin-bottom: 12px;
          background: ${containsActive ? '#eef2ff' : '#ffffff'};
          border: 2px solid ${containsActive ? 'var(--blue)' : 'var(--border)'};
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.2s ease;
          font-weight: 700;
          font-size: 18px;
        `;
        
        if (!containsActive) {
          periodDiv.onmouseenter = () => {
            periodDiv.style.backgroundColor = '#f9fafb';
            periodDiv.style.borderColor = 'var(--blue)';
          };
          periodDiv.onmouseleave = () => {
            periodDiv.style.backgroundColor = '#ffffff';
            periodDiv.style.borderColor = 'var(--border)';
          };
        }
        
        periodDiv.onclick = (e) => {
          // Don't toggle if clicking on dropdown
          if (e.target.closest('.period-sectors-toggle')) return;
          
          // Set first sector of period as active (works for both OPEN and CLOSED periods)
          if (period.status === 'CLOSED' && period.originalSectorIndex !== undefined) {
            // For CLOSED periods, use original sector index from main sectors array
            SectorBillingLogic.setActiveSector(period.originalSectorIndex);
            this.render();
          } else if (period.sectors.length > 0) {
            // For OPEN periods, find sector in main array
            const firstSectorIdx = sectors.indexOf(period.sectors[0]);
            if (firstSectorIdx >= 0) {
              SectorBillingLogic.setActiveSector(firstSectorIdx);
              this.render();
            }
          }
        };
        
        // Create dropdown for sectors if period is closed and has multiple sectors
        let sectorsToggle = '';
        if (period.status === 'CLOSED' && period.sectors.length > 1) {
          sectorsToggle = `<span class="period-sectors-toggle" style="margin-left:12px; color:var(--blue); cursor:pointer; font-size:14px; font-weight:600;">▼ ${period.sectors.length} sectors</span>`;
        }
        
        // Add "Close Period" button for OPEN periods (only if > 30 days from start date AND has readings)
        let closeButton = '';
        if (period.status === 'OPEN' && period.sectors.length > 0) {
          const firstSector = period.sectors[0];
          const firstSectorIdx = sectors.indexOf(firstSector);
          
          // Use helper function to check if close button should be shown
          if (firstSectorIdx >= 0 && SectorBillingLogic.shouldShowCloseButton(firstSectorIdx)) {
            closeButton = `<button class="btn-secondary" onclick="event.stopPropagation(); SectorBillingLogic.closeSector(${firstSectorIdx}); SectorBillingUI.render();" style="margin-left:12px; padding:6px 12px; font-size:13px; background:var(--red); color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Close Period</button>`;
          }
        }
        
        periodDiv.innerHTML = `
          <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
              <span style="color:var(--text);">${periodHeader}</span>
              ${sectorsToggle}
              ${closeButton}
            </div>
            <div style="font-size:14px; color:var(--muted); font-weight:400;">
              Usage: ${usage} L | 
              Daily: ${dailyUsage} L/day
            </div>
          </div>
        `;
        
        container.appendChild(periodDiv);
        
        // Add sectors detail (collapsible) for closed periods with multiple sectors
        if (period.status === 'CLOSED' && period.sectors.length > 1) {
          const detailDiv = document.createElement('div');
          detailDiv.className = 'period-sectors-detail hidden';
          detailDiv.style.cssText = `
            margin-left:20px;
            margin-bottom:12px;
            padding:12px;
            background:#f9fafb;
            border-left:3px solid var(--blue);
            border-radius:4px;
          `;
          
          let sectorsHtml = '<div style="font-weight:600; margin-bottom:8px; color:var(--text);">Sectors within this period:</div>';
          period.sectors.forEach((sector, sectorIdx) => {
            const sectorDisplay = SectorBillingLogic.formatDate(sector.start_date) + ' → ' + SectorBillingLogic.formatDate(sector.end_date);
            const sectorUsage = sector.total_usage !== null && sector.total_usage !== undefined 
              ? SectorBillingLogic.formatNumber(sector.total_usage) 
              : '—';
            const sectorDaily = sector.daily_usage !== null && sector.daily_usage !== undefined 
              ? SectorBillingLogic.formatNumber(sector.daily_usage) 
              : '—';
            
            sectorsHtml += `
              <div style="padding:8px; margin:4px 0; background:white; border-radius:4px; font-size:14px;">
                <strong>Sector ${sectorIdx + 1}:</strong> ${sectorDisplay} | 
                Usage: ${sectorUsage} L | 
                Daily: ${sectorDaily} L/day
              </div>
            `;
          });
          detailDiv.innerHTML = sectorsHtml;
          container.appendChild(detailDiv);
          
          // Link toggle to detail div
          const toggleSpan = periodDiv.querySelector('.period-sectors-toggle');
          if (toggleSpan) {
            toggleSpan.onclick = (e) => {
              e.stopPropagation();
              detailDiv.classList.toggle('hidden');
              toggleSpan.textContent = detailDiv.classList.contains('hidden') 
                ? `▼ ${period.sectors.length} sectors` 
                : `▲ ${period.sectors.length} sectors`;
            };
          }
        }
      });
    },
    
    renderSectorReadings: function() {
      console.log('renderSectorReadings called');
      const table = document.getElementById('sector_reading_table');
      if (!table) {
        console.error('sector_reading_table element not found!');
        return;
      }
      console.log('Table found, rendering...');
      
      // Clear and set up table header
      const tbody = table.querySelector('tbody');
      if (tbody) {
        tbody.innerHTML = '';
        console.log('Cleared existing tbody');
      } else {
        table.innerHTML = '<thead><tr><th>Date</th><th>Reading (L)</th><th>Difference (L)</th><th>Cost (R)</th><th></th></tr></thead><tbody></tbody>';
        console.log('Created new tbody');
      }
      const tbodyEl = table.querySelector('tbody');
      if (!tbodyEl) {
        console.error('tbodyEl not found after setup!');
        return;
      }
      console.log('tbodyEl found, ready to append rows');
      
      const active = SectorBillingLogic.getActiveSector();
      if (active === null) {
        // Show message if no sector selected
        const tbody = table.querySelector('tbody');
        if (tbody && tbody.children.length === 0) {
          const tr = document.createElement('tr');
          tr.innerHTML = '<td colspan="5" style="text-align:center; padding:20px; color:var(--muted);">No sector selected. Click on a period to view its readings.</td>';
          tbody.appendChild(tr);
        }
        return;
      }
      
      const sectors = SectorBillingLogic.getSectors();
      const sector = sectors[active];
      if (!sector) {
        return;
      }
      
      // Display readings even for CLOSED sectors
      if (!sector.readings || sector.readings.length === 0) {
        const tbody = table.querySelector('tbody');
        if (tbody) {
          const tr = document.createElement('tr');
          tr.innerHTML = '<td colspan="5" style="text-align:center; padding:20px; color:var(--muted);">No readings in this sector.</td>';
          tbody.appendChild(tr);
        }
        return;
      }
      
      // Display readings in insertion order (new entries appear at bottom)
      // For calculations, we'll still use sorted readings
      // Filter out the opening reading from display (it's shown in the period header)
      const openingReadingDate = sector.start_date ? SectorBillingLogic.iso(sector.start_date) : null;
      const openingReadingValue = sector.start_reading !== null && sector.start_reading !== undefined ? sector.start_reading : null;
      
      // Filter out the opening reading if it matches both date and value
      // Also filter out any readings that are before the period start date (stale/invalid readings)
      // CRITICAL: For OPEN sectors, show readings without dates (user can enter date), but for CLOSED sectors, filter them out
      // CRITICAL: Filter out readings that have values but no dates (invalid state - should not exist)
      const displayReadings = sector.readings.filter(reading => {
        // For CLOSED sectors, exclude readings without dates (should not exist in closed sectors)
        // For OPEN sectors, allow readings without dates (user can still enter date)
        if (sector.status === 'CLOSED') {
          if (!reading.date || reading.date.trim() === '' || reading.date === null || reading.date === undefined) {
            console.log('Filtering out reading - CLOSED sector without date:', reading.date, reading.value);
            return false; // CLOSED sectors should not have readings without dates
          }
        }
        
        // CRITICAL: Exclude readings that have values but no dates (invalid state)
        // This prevents calculations from using readings without dates
        if ((reading.value !== null && reading.value !== undefined) && 
            (!reading.date || reading.date.trim() === '' || reading.date === null || reading.date === undefined)) {
          console.log('Filtering out reading - has value but no date (invalid state):', reading.date, reading.value);
          return false; // Reading has value but no date - invalid state, exclude it
        }
        
        // Exclude readings before period start date
        // Normalize both dates to noon to avoid timezone/time comparison issues
        if (sector.start_date && reading.date) {
          const readingDate = new Date(reading.date);
          if (isNaN(readingDate.getTime())) {
            console.log('Filtering out reading - invalid date format:', reading.date);
            return false; // Invalid date format - exclude it
          }
          readingDate.setHours(12, 0, 0, 0); // Normalize to noon
          const startDate = new Date(sector.start_date);
          startDate.setHours(12, 0, 0, 0); // Normalize to noon
          if (readingDate < startDate) {
            console.log('Filtering out reading - before start date:', reading.date, 'start:', sector.start_date);
            return false; // Reading is before period start - exclude it
          }
        }
        
        // Keep all readings that don't match the opening reading
        // CRITICAL: Only exclude readings that are explicitly marked as the opening reading (Period 2+)
        // For Period 1, all readings are user-entered and should be displayed (including 0 values)
        // For Period 2+, the opening reading is created by closeSector and should be excluded
        // NOTE: 0 is a valid reading value (especially for new meters), so we use strict equality
        if (openingReadingDate !== null && openingReadingValue !== null && openingReadingValue !== undefined && sector.sector_id > 1) {
          // Only filter opening reading for Period 2+ when we have both date and value
          const readingDate = reading.date ? SectorBillingLogic.iso(new Date(reading.date)) : null;
          const readingValue = reading.value !== null && reading.value !== undefined ? reading.value : null;
          // Exclude ONLY if it matches both opening date AND opening value (Period 2+ only)
          // This allows 0 values in Period 1 and other readings to display correctly
          if (readingDate === openingReadingDate && readingValue === openingReadingValue) {
            console.log('Filtering out reading - matches opening reading (Period 2+):', reading.date, reading.value);
            return false; // This is the opening reading - exclude from display
          }
        }
        console.log('Keeping reading:', reading.date, reading.value);
        return true; // Keep all other readings (including all Period 1 readings and 0 values)
      });
      
      const sortedReadings = [...sector.readings].filter(r => r.date).sort((a, b) => {
        if (!a.date || !b.date) return 0;
        return new Date(a.date) - new Date(b.date);
      });
      
      let totalUsage = 0;
      
      // If no readings to display (only opening reading exists), don't return early for OPEN sectors
      // This allows the "Add Reading" button to work immediately after Period 1 is created
      if (displayReadings.length === 0) {
        const tbody = table.querySelector('tbody');
        if (tbody) {
          // Only show message if sector is CLOSED (can't add readings to closed sectors)
          if (sector.status === 'CLOSED') {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="5" style="text-align:center; padding:20px; color:var(--muted);">No additional readings. Opening reading is shown in the period header above.</td>';
            tbody.appendChild(tr);
          }
          // For OPEN sectors, don't show message and don't return early
          // This ensures the table is ready to accept the first reading when "Add Reading" is clicked
        }
        // Only return early for CLOSED sectors (can't add readings to closed sectors)
        if (sector.status === 'CLOSED') {
          return;
        }
        // For OPEN sectors, continue to allow adding readings even if displayReadings is empty
      }
      
      console.log('About to render displayReadings, count:', displayReadings.length, 'readings:', displayReadings);
      displayReadings.forEach((reading, i) => {
        console.log('Rendering reading row', i, 'date:', reading.date, 'value:', reading.value);
        const tr = document.createElement('tr');
        
        // Find this reading's index in sorted order (needed for first reading check)
        const readingIndexInSorted = sortedReadings.findIndex(r => r === reading);
        
        // Check if this is Period 1 (sector_id === 1) - only Period 1 has auto-inserted opening reading
        // For Period 2+, all readings have user-selectable dates
        const isPeriod1 = sector.sector_id === 1;
        const isFirstReading = readingIndexInSorted === 0 && isPeriod1;
        
        // Date selector - use input type="date" for better UX
        const dateInput = document.createElement('input');
        dateInput.type = 'date';
        dateInput.className = 'input-date';
        dateInput.value = reading.date || ''; // Empty if date is null - user will select
        
        // Set min date based on previous readings or start_date
        // Get all readings with dates (excluding current reading) to determine min
        const readingsWithDates = sector.readings.filter(r => r !== reading && r.date);
        if (readingsWithDates.length > 0) {
          // Sort by date and get the latest one
          const sorted = [...readingsWithDates].sort((a, b) => new Date(a.date) - new Date(b.date));
          const lastReading = sorted[sorted.length - 1];
          if (lastReading && lastReading.date) {
            const minDate = new Date(lastReading.date);
            minDate.setDate(minDate.getDate() + 1); // Day after last reading
            minDate.setHours(12, 0, 0, 0);
            dateInput.min = SectorBillingLogic.iso(minDate);
          }
        } else if (sector.start_date) {
          // No readings with dates yet - use start_date as min
          dateInput.min = SectorBillingLogic.iso(new Date(sector.start_date));
        }
        
        // Disable editing only for CLOSED sectors
        // All readings in OPEN sectors have selectable dates (user chooses date in row)
        if (sector.status === 'CLOSED') {
          dateInput.disabled = true;
          dateInput.style.backgroundColor = '#f3f4f6';
          dateInput.style.cursor = 'not-allowed';
        }
        dateInput.onchange = () => {
          // Don't allow changes to CLOSED sectors
          if (sector.status === 'CLOSED') {
            dateInput.value = reading.date || '';
            return;
          }
          // All readings in OPEN sectors have selectable dates
          // CRITICAL: Date is required - cannot save reading without a valid date
          const newDate = dateInput.value;
          
          // Validate: date is REQUIRED
          if (!newDate || newDate.trim() === '') {
            alert('Date is required. Please select a date before entering a reading value.');
            dateInput.value = reading.date || '';
            dateInput.focus();
            return;
          }
          
          // Validate: date must not be earlier than previous reading and must be unique
          // Get all readings with dates (excluding current reading)
          const otherReadings = sector.readings.filter(r => r !== reading && r.date);
          
          // Check for duplicate dates
          const duplicateDate = otherReadings.find(r => r.date === newDate);
          if (duplicateDate) {
            alert(`This date (${newDate}) is already used by another reading. Please enter a different date.`);
            dateInput.value = reading.date || '';
            dateInput.focus();
            return;
          }
          
          if (otherReadings.length > 0) {
            // Sort by date
            const sorted = [...otherReadings].sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Find the latest reading before the new date
            const readingsBefore = sorted.filter(r => new Date(r.date) < new Date(newDate));
            
            // Find the earliest reading after the new date
            const readingsAfter = sorted.filter(r => new Date(r.date) > new Date(newDate));
            
            // Check: new date must be >= the latest date before it
            const latestBefore = readingsBefore.length > 0 ? readingsBefore[readingsBefore.length - 1] : null;
            if (latestBefore && new Date(newDate) < new Date(latestBefore.date)) {
              alert(`Date cannot be earlier than the previous reading date (${latestBefore.date}). Please enter a date on or after ${latestBefore.date}.`);
              dateInput.value = reading.date || '';
              dateInput.focus();
              return;
            }
            
            // Check: new date must be <= the earliest date after it
            const earliestAfter = readingsAfter.length > 0 ? readingsAfter[0] : null;
            if (earliestAfter && new Date(newDate) > new Date(earliestAfter.date)) {
              alert(`Date cannot be later than the next reading date (${earliestAfter.date}). Please enter a date on or before ${earliestAfter.date}.`);
              dateInput.value = reading.date || '';
              dateInput.focus();
              return;
            }
          }
          
          // Only save date if it's valid (non-empty)
          reading.date = newDate;
          
          // CRITICAL: Period does NOT close when date is selected
          // Period only closes when BOTH date AND reading value are entered
          // Auto-close logic is now in valueInput.onblur (when reading value is entered)
          
          // Update current_date when user changes date (this becomes the "current date")
          SectorBillingLogic.setCurrentDate(newDate);
          // Explicitly update sectors array to ensure persistence
          const sectors = SectorBillingLogic.getSectors();
          SectorBillingLogic.setSectors(sectors);
          
          // Update date picker default after date change
          this.updateDatePickerDefault();
          
          this.render();
        };
        
        // Reading input
        const valueInput = document.createElement('input');
        valueInput.type = 'number';
        valueInput.className = 'input-reading';
        valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
        // Disable editing for CLOSED sectors (read-only)
        if (sector.status === 'CLOSED') {
          valueInput.disabled = true;
          valueInput.style.backgroundColor = '#f3f4f6';
          valueInput.style.cursor = 'not-allowed';
        }
        valueInput.onblur = () => {
          // Don't allow changes to CLOSED sectors
          if (sector.status === 'CLOSED') {
            valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
            return;
          }
          
          // CRITICAL: Date is REQUIRED before entering a reading value
          if (!reading.date || reading.date.trim() === '') {
            alert('Date is required. Please select a date before entering a reading value.');
            valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
            // Focus on date input instead
            const dateInput = tr.querySelector('.input-date');
            if (dateInput) {
              dateInput.focus();
            }
            return;
          }
          
          // Only update when user leaves the field
          // CRITICAL: 0 is a valid reading value (especially for new meters), so check for empty string explicitly
          const newValue = valueInput.value !== '' && valueInput.value !== null && valueInput.value !== undefined 
            ? Number(valueInput.value) 
            : null;
          
          // Validate: reading must not be lower than previous reading (by date order) OR opening reading from previous period
          // CRITICAL: 0 is a valid reading value (especially for new meters in Period 1)
          if (newValue !== null && !isNaN(newValue)) {
            // Get all readings with dates and values (excluding current reading)
            const otherReadings = sector.readings.filter(r => r !== reading && r.date && r.value !== null);
            
            // Sort by date to find previous and next readings
            const sortedOther = [...otherReadings].sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Check if this is the first reading in a new period (Period 2+)
            // If so, we need to check against the previous period's closing reading
            const allSectors = SectorBillingLogic.getSectors();
            const currentSectorIndex = allSectors.indexOf(sector);
            let minimumAllowedValue = null;
            
            // If this is Period 2+ (not the first sector), check previous period's closing reading
            if (currentSectorIndex > 0) {
              const previousSector = allSectors[currentSectorIndex - 1];
              if (previousSector && previousSector.status === 'CLOSED' && previousSector.end_reading !== null) {
                minimumAllowedValue = previousSector.end_reading;
              }
            }
            
            // Find readings before and after this reading's date
            if (reading.date) {
              const readingsBefore = sortedOther.filter(r => new Date(r.date) < new Date(reading.date));
              const readingsAfter = sortedOther.filter(r => new Date(r.date) > new Date(reading.date));
              
              // Check against latest previous reading (by date)
              if (readingsBefore.length > 0) {
                const latestBefore = readingsBefore[readingsBefore.length - 1];
                if (newValue < latestBefore.value) {
                  alert(`Reading value (${newValue} L) cannot be lower than the previous reading (${latestBefore.value} L on ${latestBefore.date}). Please enter a value of ${latestBefore.value} L or higher.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
                // Update minimum if previous reading is higher
                if (minimumAllowedValue === null || latestBefore.value > minimumAllowedValue) {
                  minimumAllowedValue = latestBefore.value;
                }
              }
              
              // Check against opening reading from previous period (for first reading in new period)
              if (minimumAllowedValue !== null && newValue < minimumAllowedValue) {
                alert(`Reading value (${newValue} L) cannot be lower than the opening reading from the previous period (${minimumAllowedValue} L). Please enter a value of ${minimumAllowedValue} L or higher.`);
                valueInput.value = reading.value !== null ? reading.value : '';
                return;
              }
              
              // Check against earliest next reading (by date)
              if (readingsAfter.length > 0) {
                const earliestAfter = readingsAfter[0];
                if (newValue > earliestAfter.value) {
                  alert(`Reading value (${newValue} L) cannot be higher than the next reading (${earliestAfter.value} L on ${earliestAfter.date}). Please enter a value of ${earliestAfter.value} L or lower.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
              }
            } else {
              // If no date, check against all readings with values AND opening reading
              if (sortedOther.length > 0) {
                const minValue = Math.min(...sortedOther.map(r => r.value));
                const effectiveMin = minimumAllowedValue !== null && minimumAllowedValue > minValue ? minimumAllowedValue : minValue;
                if (newValue < effectiveMin) {
                  alert(`Reading value (${newValue} L) cannot be lower than existing readings${minimumAllowedValue !== null ? ` or the opening reading from the previous period` : ''}. Minimum value is ${effectiveMin} L.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
              } else if (minimumAllowedValue !== null && newValue < minimumAllowedValue) {
                // No other readings, but we have a minimum from previous period
                alert(`Reading value (${newValue} L) cannot be lower than the opening reading from the previous period (${minimumAllowedValue} L). Please enter a value of ${minimumAllowedValue} L or higher.`);
                valueInput.value = reading.value !== null ? reading.value : '';
                return;
              }
            }
          }
          
          reading.value = newValue;
          // Update current_date when user enters a reading (use the reading's date as current date)
          if (reading.date && newValue !== null) {
            SectorBillingLogic.setCurrentDate(reading.date);
          }
          
          // AUTO-CLOSE/auto-create logic: If reading date is >30 days from period start, auto-close period
          // The reading that triggers close (>30 days from start) BELONGS to the OLD period and CLOSES it
          if (reading.date && newValue !== null && !isNaN(newValue) && sector.status === 'OPEN') {
            const readingDate = new Date(reading.date);
            readingDate.setHours(12, 0, 0, 0);
            const periodStartDate = new Date(sector.start_date);
            periodStartDate.setHours(12, 0, 0, 0);
            
            // Calculate days from period start to reading date
            const daysFromStart = Math.floor((readingDate - periodStartDate) / 86400000);
            
            // Check if reading date is >30 days from period start (triggers period close)
            if (daysFromStart > 30) {
              console.log('Auto-closing period: Reading date', reading.date, 'with value', newValue, 'is >30 days from period start', SectorBillingLogic.iso(periodStartDate));
              
              // The triggering reading BELONGS to this period and CLOSES it
              // Set period.end_date to the closing reading's date
              sector.end_date = new Date(reading.date);
              sector.status = 'CLOSED';
              
              // Get all readings in period (including the closing reading)
              const readingsInPeriod = sector.readings.filter(r => {
                if (!r.date || r.value === null || r.value === undefined) return false;
                const rDate = new Date(r.date);
                rDate.setHours(12, 0, 0, 0);
                const periodEndDate = new Date(sector.end_date);
                periodEndDate.setHours(12, 0, 0, 0);
                // Include readings on or before the closing date
                return rDate <= periodEndDate;
              });
              
              // Update period closing reading and totals
              if (readingsInPeriod.length > 0) {
                const sortedReadings = [...readingsInPeriod].sort((a, b) => new Date(a.date) - new Date(b.date));
                // Closing reading is the last reading (the one that triggered the close)
                const closingReading = sortedReadings[sortedReadings.length - 1];
                sector.end_reading = closingReading.value;
                
                // Set start_reading if not already set (first reading in period)
                if (sector.start_reading === 0 || sector.start_reading === null || sector.start_reading === undefined) {
                  sector.start_reading = sortedReadings[0].value;
                }
                
                sector.total_usage = sector.end_reading - sector.start_reading;
                
                // Calculate days from period start to closing reading date (inclusive end)
                const startDate = new Date(sector.start_date);
                startDate.setHours(0, 0, 0, 0);
                const endDate = new Date(sector.end_date);
                endDate.setHours(0, 0, 0, 0);
                // For period length calculation: inclusive start to inclusive end = add 1 day
                sector.days = Math.floor((endDate - startDate) / 86400000) + 1;
                
                if (sector.days > 0) {
                  sector.daily_usage = sector.total_usage / sector.days;
                } else {
                  sector.daily_usage = 0;
                }
                
                // Calculate tier costs
                const tierResult = calculate_tier_cost(sector.total_usage, sector_tiers);
                sector.tier_cost = tierResult.total;
                sector.tier_items = tierResult.items;
              }
              
              console.log('Period closed at closing reading date:', SectorBillingLogic.iso(sector.end_date), ', closing reading:', sector.end_reading);
              
              // Create new period starting the day AFTER the closing reading
              const newPeriodStartDate = new Date(sector.end_date);
              newPeriodStartDate.setDate(newPeriodStartDate.getDate() + 1);
              newPeriodStartDate.setHours(12, 0, 0, 0);
              
              const allSectors = SectorBillingLogic.getSectors();
              const newSectorId = allSectors.length + 1;
              
              // Opening reading for new period = previous period's closing reading
              const newPeriod = {
                sector_id: newSectorId,
                start_date: newPeriodStartDate,
                end_date: null,
                start_reading: sector.end_reading !== null && sector.end_reading !== undefined ? sector.end_reading : newValue,
                end_reading: null, // Will be set when readings are added
                total_usage: 0,
                daily_usage: 0,
                days: 0,
                status: 'OPEN',
                readings: [], // New period starts empty
                tier_cost: 0,
                tier_items: []
              };
              
              console.log('Creating new period - start_date:', SectorBillingLogic.iso(newPeriodStartDate), ', opening reading:', newPeriod.start_reading);
              
              // The closing reading stays in the old period (it belongs there)
              // No need to remove it or reassign it
              
              allSectors.push(newPeriod);
              SectorBillingLogic.setSectors(allSectors);
              
              // Keep old period as active (since the closing reading is in it)
              // Don't change active sector - user can manually select new period if needed
              
              // Sector totals for old period are already updated above
              // New period totals will be updated by the code below when readings are added
            }
          }
          
          // Update sector totals
          if (sector.readings && sector.readings.length > 0) {
            const sortedReadings = [...sector.readings].filter(r => r.date && r.value !== null).sort((a, b) => new Date(a.date) - new Date(b.date));
            if (sortedReadings.length > 0) {
              const firstReading = sortedReadings[0];
              const lastReading = sortedReadings[sortedReadings.length - 1];
              
              // For Period 1: first reading value becomes start_reading
              // For Period 2+: start_reading is already set from previous period's end_reading
              if (sector.start_reading === 0 || sector.start_reading === null || sector.start_reading === undefined) {
                // Period 1: first reading value becomes the start_reading
                sector.start_reading = firstReading.value;
              }
              // For Period 2+, start_reading is already set correctly from previous period
              
              sector.end_reading = lastReading.value;
              sector.total_usage = sector.end_reading - sector.start_reading;
              
              // For CLOSED periods, use end_date; for OPEN periods, use last reading date
              const periodEndDate = sector.status === 'CLOSED' && sector.end_date 
                ? new Date(sector.end_date) 
                : (lastReading.date ? new Date(lastReading.date) : null);
              
              if (sector.start_date && periodEndDate) {
                const startDate = new Date(sector.start_date);
                startDate.setHours(12, 0, 0, 0);
                const endDate = new Date(periodEndDate);
                endDate.setHours(12, 0, 0, 0);
                // Calculate days using inclusive-inclusive (daysBetween adds 1)
                sector.days = SectorBillingLogic.daysBetween(startDate, endDate);
                if (sector.days > 0) {
                  sector.daily_usage = sector.total_usage / sector.days;
                } else {
                  sector.daily_usage = 0;
                }
              } else {
                sector.days = 0;
                sector.daily_usage = 0;
              }
              
              // IMPORTANT: If Period 1 is CLOSED and this is the closing reading, update Period 2's start_reading
              // When a reading >30 days is entered, Period 1 closes and Period 2 is created
              // The closing reading of Period 1 becomes the opening reading of Period 2
              if (sector.status === 'CLOSED' && reading.date && reading.value !== null && reading.value !== undefined) {
                // Check if this reading is the closing reading (matches end_date)
                const readingDate = new Date(reading.date);
                readingDate.setHours(12, 0, 0, 0);
                const endDate = sector.end_date ? new Date(sector.end_date) : null;
                if (endDate) {
                  endDate.setHours(12, 0, 0, 0);
                  // If this reading's date matches Period 1's end_date, it's the closing reading
                  if (readingDate.getTime() === endDate.getTime()) {
                    // This is the closing reading - update Period 1's end_reading (already done above, but ensure it's set)
                    sector.end_reading = reading.value;
                    sector.total_usage = sector.end_reading - sector.start_reading;
                    sector.daily_usage = sector.days > 0 ? sector.total_usage / sector.days : 0;
                    
                    // Update Period 2's start_reading to match Period 1's closing reading (same value)
                    const allSectors = SectorBillingLogic.getSectors();
                    const currentSectorIndex = allSectors.indexOf(sector);
                    if (currentSectorIndex >= 0 && currentSectorIndex < allSectors.length - 1) {
                      const nextSector = allSectors[currentSectorIndex + 1];
                      if (nextSector && nextSector.status === 'OPEN') {
                        nextSector.start_reading = sector.end_reading; // Opening reading = closing reading of Period 1
                        console.log('Updated Period 2 start_reading to match Period 1 closing reading:', sector.end_reading);
                        SectorBillingLogic.setSectors(allSectors);
                        
                        // Recalculate Period 2's usage (it might have readings already)
                        SectorBillingLogic.calculateSector(currentSectorIndex + 1);
                      }
                    }
                  }
                }
              }
              
              // Don't automatically calculate tier costs - require manual calculation via Calculate button
              // Tier costs will be calculated when user clicks Calculate button
            }
          }
          
          this.render();
        };
        valueInput.onchange = () => {
          // Don't allow changes to CLOSED sectors
          if (sector.status === 'CLOSED') {
            valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
            return;
          }
          
          // Also update on change (Enter key, etc.)
          // CRITICAL: Date is REQUIRED before entering a reading value
          if (!reading.date || reading.date.trim() === '') {
            alert('Date is required. Please select a date before entering a reading value.');
            valueInput.value = reading.value !== null && reading.value !== undefined ? reading.value : '';
            // Focus on date input instead
            const dateInput = tr.querySelector('.input-date');
            if (dateInput) {
              dateInput.focus();
            }
            return;
          }
          
          // CRITICAL: 0 is a valid reading value (especially for new meters), so check for empty string explicitly
          const newValue = valueInput.value !== '' && valueInput.value !== null && valueInput.value !== undefined 
            ? Number(valueInput.value) 
            : null;
          
          // Update current_date when user enters a reading (use the reading's date as current date)
          if (reading.date && newValue !== null) {
            SectorBillingLogic.setCurrentDate(reading.date);
          }
          
          // Validate: reading must not be lower than previous reading (by date order) OR opening reading from previous period
          if (newValue !== null && !isNaN(newValue)) {
            // Get all readings with dates and values (excluding current reading)
            const otherReadings = sector.readings.filter(r => r !== reading && r.date && r.value !== null);
            
            // Sort by date to find previous and next readings
            const sortedOther = [...otherReadings].sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Check if this is the first reading in a new period (Period 2+)
            // If so, we need to check against the previous period's closing reading
            const allSectors = SectorBillingLogic.getSectors();
            const currentSectorIndex = allSectors.indexOf(sector);
            let minimumAllowedValue = null;
            
            // If this is Period 2+ (not the first sector), check previous period's closing reading
            if (currentSectorIndex > 0) {
              const previousSector = allSectors[currentSectorIndex - 1];
              if (previousSector && previousSector.status === 'CLOSED' && previousSector.end_reading !== null) {
                minimumAllowedValue = previousSector.end_reading;
              }
            }
            
            // Find readings before and after this reading's date
            if (reading.date) {
              const readingsBefore = sortedOther.filter(r => new Date(r.date) < new Date(reading.date));
              const readingsAfter = sortedOther.filter(r => new Date(r.date) > new Date(reading.date));
              
              // Check against latest previous reading (by date)
              if (readingsBefore.length > 0) {
                const latestBefore = readingsBefore[readingsBefore.length - 1];
                if (newValue < latestBefore.value) {
                  alert(`Reading value (${newValue} L) cannot be lower than the previous reading (${latestBefore.value} L on ${latestBefore.date}). Please enter a value of ${latestBefore.value} L or higher.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
                // Update minimum if previous reading is higher
                if (minimumAllowedValue === null || latestBefore.value > minimumAllowedValue) {
                  minimumAllowedValue = latestBefore.value;
                }
              }
              
              // Check against opening reading from previous period (for first reading in new period)
              if (minimumAllowedValue !== null && newValue < minimumAllowedValue) {
                alert(`Reading value (${newValue} L) cannot be lower than the opening reading from the previous period (${minimumAllowedValue} L). Please enter a value of ${minimumAllowedValue} L or higher.`);
                valueInput.value = reading.value !== null ? reading.value : '';
                return;
              }
              
              // Check against earliest next reading (by date)
              if (readingsAfter.length > 0) {
                const earliestAfter = readingsAfter[0];
                if (newValue > earliestAfter.value) {
                  alert(`Reading value (${newValue} L) cannot be higher than the next reading (${earliestAfter.value} L on ${earliestAfter.date}). Please enter a value of ${earliestAfter.value} L or lower.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
              }
            } else {
              // If no date, check against all readings with values AND opening reading
              if (sortedOther.length > 0) {
                const minValue = Math.min(...sortedOther.map(r => r.value));
                const effectiveMin = minimumAllowedValue !== null && minimumAllowedValue > minValue ? minimumAllowedValue : minValue;
                if (newValue < effectiveMin) {
                  alert(`Reading value (${newValue} L) cannot be lower than existing readings${minimumAllowedValue !== null ? ` or the opening reading from the previous period` : ''}. Minimum value is ${effectiveMin} L.`);
                  valueInput.value = reading.value !== null ? reading.value : '';
                  return;
                }
              } else if (minimumAllowedValue !== null && newValue < minimumAllowedValue) {
                // No other readings, but we have a minimum from previous period
                alert(`Reading value (${newValue} L) cannot be lower than the opening reading from the previous period (${minimumAllowedValue} L). Please enter a value of ${minimumAllowedValue} L or higher.`);
                valueInput.value = reading.value !== null ? reading.value : '';
                return;
              }
            }
          }
          
          reading.value = newValue;
          
          // AUTO-CLOSE/auto-create logic: If reading date is >30 days from period start AND reading value is entered, auto-close Period 1 and create Period 2
          // A period is closed when the FIRST reading AFTER 30 days is input (date AND value)
          // IMPORTANT: The current reading (>30 days) STAYS in Period 1 as the closing reading
          // Period 2 starts the day after, with the SAME reading value as Period 1's closing reading (opening reading)
          if (reading.date && newValue !== null && !isNaN(newValue) && sector.status === 'OPEN') {
            const readingDate = new Date(reading.date);
            readingDate.setHours(12, 0, 0, 0);
            const periodStartDate = new Date(sector.start_date);
            periodStartDate.setHours(12, 0, 0, 0);
            const daysFromStart = SectorBillingLogic.daysBetween(periodStartDate, readingDate);
            
            if (daysFromStart > 30) {
              console.log('Auto-closing Period 1 (onchange): Reading date', reading.date, 'with value', newValue, 'is', daysFromStart, 'days from start', SectorBillingLogic.iso(periodStartDate));
              
              // IMPORTANT: The current reading (>30 days) becomes Period 1's closing reading
              // Period 1 closes at the current reading's date
              // The current reading STAYS in Period 1 (not moved to Period 2)
              sector.end_date = new Date(readingDate);
              sector.status = 'CLOSED';
              
              console.log('Period 1 closing at current reading date:', SectorBillingLogic.iso(sector.end_date), ', reading value:', newValue);
              
              // IMPORTANT: The current reading STAYS in Period 1 - do NOT move it to Period 2
              // Period 1 will have all readings up to and including the current reading (20th Feb)
              
              // Create Period 2 starting the day after the current reading's date
              const period2StartDate = new Date(readingDate);
              period2StartDate.setDate(period2StartDate.getDate() + 1);
              period2StartDate.setHours(12, 0, 0, 0);
              
              const allSectors = SectorBillingLogic.getSectors();
              const newSectorId = allSectors.length + 1;
              
              // Period 2's start_reading will be set to Period 1's end_reading after sector totals are updated below
              // For now, set it to the current reading value (temporary, will be updated by sector totals logic)
              const period2 = {
                sector_id: newSectorId,
                start_date: period2StartDate,
                end_date: null,
                start_reading: newValue, // Opening reading = Period 1's closing reading (temporary, will be updated below)
                end_reading: 0,
                total_usage: 0,
                daily_usage: 0,
                days: 0,
                status: 'OPEN',
                readings: [], // Period 2 starts empty - no readings yet
                tier_cost: 0,
                tier_items: []
              };
              
              console.log('Creating Period 2 (onchange) - start_date:', SectorBillingLogic.iso(period2StartDate), ', temporary start_reading:', period2.start_reading, '(will be updated to Period 1 end_reading)');
              
              allSectors.push(period2);
              SectorBillingLogic.setSectors(allSectors);
              // Keep Period 1 as active (since the current reading is still in Period 1)
              SectorBillingLogic.setActiveSector(active);
              
              // Sector totals will be updated by the code below
              // Period 2's start_reading will be updated to Period 1's end_reading in the logic below
            }
          }
          
          // Update sector totals
          if (sector.readings && sector.readings.length > 0) {
            const sortedReadings = [...sector.readings].filter(r => r.date && r.value !== null).sort((a, b) => new Date(a.date) - new Date(b.date));
            if (sortedReadings.length > 0) {
              const firstReading = sortedReadings[0];
              const lastReading = sortedReadings[sortedReadings.length - 1];
              
              // For Period 1: first reading value becomes start_reading
              // For Period 2+: start_reading is already set from previous period's end_reading
              if (sector.start_reading === 0 || sector.start_reading === null || sector.start_reading === undefined) {
                // Period 1: first reading value becomes the start_reading
                sector.start_reading = firstReading.value;
              }
              // For Period 2+, start_reading is already set correctly from previous period
              
              sector.end_reading = lastReading.value;
              sector.total_usage = sector.end_reading - sector.start_reading;
              
              // For CLOSED periods, use end_date; for OPEN periods, use last reading date
              const periodEndDate = sector.status === 'CLOSED' && sector.end_date 
                ? new Date(sector.end_date) 
                : (lastReading.date ? new Date(lastReading.date) : null);
              
              if (sector.start_date && periodEndDate) {
                const startDate = new Date(sector.start_date);
                startDate.setHours(12, 0, 0, 0);
                const endDate = new Date(periodEndDate);
                endDate.setHours(12, 0, 0, 0);
                // Calculate days using inclusive-inclusive (daysBetween adds 1)
                sector.days = SectorBillingLogic.daysBetween(startDate, endDate);
                if (sector.days > 0) {
                  sector.daily_usage = sector.total_usage / sector.days;
                } else {
                  sector.daily_usage = 0;
                }
              } else {
                sector.days = 0;
                sector.daily_usage = 0;
              }
              
              // IMPORTANT: If Period 1 is CLOSED and this is the closing reading, update Period 2's start_reading
              // When a reading >30 days is entered, Period 1 closes and Period 2 is created
              // The closing reading of Period 1 becomes the opening reading of Period 2
              if (sector.status === 'CLOSED' && reading.date && reading.value !== null && reading.value !== undefined) {
                // Check if this reading is the closing reading (matches end_date)
                const readingDate = new Date(reading.date);
                readingDate.setHours(12, 0, 0, 0);
                const endDate = sector.end_date ? new Date(sector.end_date) : null;
                if (endDate) {
                  endDate.setHours(12, 0, 0, 0);
                  // If this reading's date matches Period 1's end_date, it's the closing reading
                  if (readingDate.getTime() === endDate.getTime()) {
                    // This is the closing reading - update Period 1's end_reading (already done above, but ensure it's set)
                    sector.end_reading = reading.value;
                    sector.total_usage = sector.end_reading - sector.start_reading;
                    sector.daily_usage = sector.days > 0 ? sector.total_usage / sector.days : 0;
                    
                    // Update Period 2's start_reading to match Period 1's closing reading (same value)
                    const allSectors = SectorBillingLogic.getSectors();
                    const currentSectorIndex = allSectors.indexOf(sector);
                    if (currentSectorIndex >= 0 && currentSectorIndex < allSectors.length - 1) {
                      const nextSector = allSectors[currentSectorIndex + 1];
                      if (nextSector && nextSector.status === 'OPEN') {
                        nextSector.start_reading = sector.end_reading; // Opening reading = closing reading of Period 1
                        console.log('Updated Period 2 start_reading (onchange) to match Period 1 closing reading:', sector.end_reading);
                        SectorBillingLogic.setSectors(allSectors);
                      }
                    }
                  }
                }
              }
              
              // Don't automatically calculate tier costs - require manual calculation via Calculate button
              // Tier costs will be calculated when user clicks Calculate button
            }
          }
          
          this.render();
        };
        
        // Calculate difference (volume between this and previous reading)
        // Use sector.start_reading as the baseline (opening reading from period header)
        const baselineReading = sector.start_reading !== null && sector.start_reading !== undefined ? sector.start_reading : 0;
        
        // Find previous reading in sorted order (for difference calculation)
        const previousReadingInSorted = readingIndexInSorted > 0 ? sortedReadings[readingIndexInSorted - 1] : null;
        
        let difference = 0;
        let cost = 0;
        
        if (reading.value !== null && reading.value !== undefined) {
          if (previousReadingInSorted && previousReadingInSorted.value !== null) {
            // Not the first reading - calculate difference from previous reading
            difference = reading.value - previousReadingInSorted.value;
            if (difference > 0) {
              // Calculate cost correctly: cost from baseline to current reading minus cost from baseline to previous reading
              // This ensures incremental costs sum to the total cost
              const currentReadingFromBaseline = reading.value - baselineReading;
              const previousReadingFromBaseline = previousReadingInSorted.value - baselineReading;
              
              // Calculate tier cost from baseline to current reading
              const currentTierResult = SectorBillingLogic.calculateTierCost(currentReadingFromBaseline);
              // Calculate tier cost from baseline to previous reading
              const previousTierResult = SectorBillingLogic.calculateTierCost(previousReadingFromBaseline);
              
              // Cost for this period = difference between cumulative costs
              cost = currentTierResult.total - previousTierResult.total;
              totalUsage += difference;
            }
          } else {
            // This is the first reading after the opening reading (which is filtered out)
            // Calculate difference from opening reading (baseline)
            difference = reading.value - baselineReading;
            if (difference > 0) {
              const currentReadingFromBaseline = reading.value - baselineReading;
              const tierResult = SectorBillingLogic.calculateTierCost(currentReadingFromBaseline);
              cost = tierResult.total;
              totalUsage += difference;
            }
          }
        }
        
        // Difference cell
        const diffCell = document.createElement('td');
        diffCell.style.textAlign = 'right';
        diffCell.style.fontFamily = 'ui-monospace, monospace';
        diffCell.textContent = difference > 0 ? SectorBillingLogic.formatNumber(difference) : '—';
        
        // Cost cell
        const costCell = document.createElement('td');
        costCell.style.textAlign = 'right';
        costCell.style.fontFamily = 'ui-monospace, monospace';
        costCell.style.fontWeight = '600';
        costCell.textContent = cost > 0 ? 'R' + cost.toFixed(2) : '—';
        
        // Delete button (disabled for CLOSED sectors)
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn-icon';
        deleteBtn.textContent = '✕';
        if (sector.status === 'CLOSED') {
          deleteBtn.disabled = true;
          deleteBtn.style.opacity = '0.3';
          deleteBtn.style.cursor = 'not-allowed';
          deleteBtn.title = 'Cannot delete readings from closed periods';
        } else {
          deleteBtn.onclick = () => {
            const idx = sector.readings.indexOf(reading);
            if (idx > -1) {
              sector.readings.splice(idx, 1);
              SectorBillingUI.save_revision('Reading Deleted', `Date: ${reading.date}, Value: ${reading.value} L`);
              this.render();
            }
          };
        }
        
        tr.appendChild(document.createElement('td')).appendChild(dateInput);
        tr.appendChild(document.createElement('td')).appendChild(valueInput);
        tr.appendChild(diffCell);
        tr.appendChild(costCell);
        tr.appendChild(document.createElement('td')).appendChild(deleteBtn);
        console.log('Appending row to tbody:', tr);
        tbodyEl.appendChild(tr);
        console.log('Row appended, tbody now has', tbodyEl.children.length, 'children');
      });
      console.log('Finished rendering all readings, total rows:', tbodyEl.children.length);
      
      // Dashboard is updated by updateDashboard() function called from render()
    },
    
    renderSectorOutput: function() {
      const container = document.getElementById('sector_output_container');
      if (!container) return;
      
      const active = SectorBillingLogic.getActiveSector();
      if (active === null) {
        container.innerHTML = '<div class="output-section"><div class="output-header">No Sector Selected</div></div>';
        return;
      }
      
      const sectors = SectorBillingLogic.getSectors();
      const sector = sectors[active];
      if (!sector) return;
      
      // Calculate sector if needed
      SectorBillingLogic.calculateSector(active);
      
      let html = '';
      
      // Meter Readings Section
      if (sector.readings && sector.readings.length > 0) {
        html += `<div class="output-section">
          <div class="output-header readings" onclick="this.parentElement.classList.toggle('collapsed')">Meter Readings</div>
          <div class="output-content">
            <div class="output-grid">`;
        
        sector.readings.forEach((r, i) => {
          html += `<div class="output-field">
            <div class="output-label">Reading ${i + 1}</div>
            <div class="output-value">${r.date} → ${SectorBillingLogic.formatNumber(r.value)} L</div>
          </div>`;
        });
        
        html += `</div></div></div>`;
      }
      
      // Sector Information
      // For CLOSED periods with multiple reading-based sectors, show all sectors within the period
      // For OPEN periods or single-sector periods, show the period summary
      let periodSectors = [];
      if (sector.status === 'CLOSED' && sector.readings && sector.readings.length > 1) {
        // Reconstruct reading-based sectors from readings (same logic as renderSectorTable)
        const periodEndDate = new Date(sector.end_date);
        periodEndDate.setHours(12, 0, 0, 0);
        
        const readingsInPeriod = sector.readings.filter(r => {
          if (!r.date || r.value === null || r.value === undefined) return false;
          const rDate = new Date(r.date);
          rDate.setHours(12, 0, 0, 0);
          return rDate <= periodEndDate;
        });
        
        if (readingsInPeriod.length > 1) {
          const sortedReadings = [...readingsInPeriod].sort((a, b) => new Date(a.date) - new Date(b.date));
          
          for (let j = 0; j < sortedReadings.length - 1; j++) {
            const earlier = sortedReadings[j];
            const later = sortedReadings[j + 1];
            
            const sectorStartDate = new Date(earlier.date);
            sectorStartDate.setHours(12, 0, 0, 0);
            const sectorEndDate = new Date(later.date);
            sectorEndDate.setHours(12, 0, 0, 0);
            
            const sectorDays = SectorBillingLogic.daysBetween(sectorStartDate, sectorEndDate);
            const sectorUsage = later.value - earlier.value;
            const sectorDailyUsage = sectorDays > 0 ? sectorUsage / sectorDays : 0;
            
            periodSectors.push({
              sector_id: periodSectors.length + 1,
              start_date: sectorStartDate,
              end_date: sectorEndDate,
              start_reading: earlier.value,
              end_reading: later.value,
              total_usage: sectorUsage,
              daily_usage: sectorDailyUsage,
              days: sectorDays,
              status: 'CLOSED'
            });
          }
        }
      }
      
      // If we have multiple sectors within the period, show them all
      if (periodSectors.length > 1) {
        html += `<div class="output-section">
          <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')">Sector Information (${periodSectors.length} sectors in period)</div>
          <div class="output-content">`;
        
        // Period summary
        html += `<div style="margin-bottom: 16px; padding: 12px; background: #f9fafb; border-left: 3px solid var(--blue); border-radius: 4px;">
          <div style="font-weight: 600; margin-bottom: 8px; color: var(--text);">Period Summary</div>
          <div class="output-grid">
            <div class="output-field">
              <div class="output-label">Start Date</div>
              <div class="output-value">${SectorBillingLogic.formatDate(sector.start_date)}</div>
            </div>
            <div class="output-field">
              <div class="output-label">End Date</div>
              <div class="output-value">${SectorBillingLogic.formatDate(sector.end_date)}</div>
            </div>
            <div class="output-field">
              <div class="output-label">Days</div>
              <div class="output-value">${sector.days || 0}</div>
            </div>
            <div class="output-field">
              <div class="output-label">Status</div>
              <div class="output-value"><span class="badge ${sector.status}">${sector.status}</span></div>
            </div>
          </div>
        </div>`;
        
        // Individual sectors
        html += `<div style="font-weight: 600; margin-bottom: 8px; color: var(--text);">Sectors within this period:</div>`;
        periodSectors.forEach((s, idx) => {
          html += `<div style="margin-bottom: 12px; padding: 12px; background: white; border: 1px solid #e5e7eb; border-radius: 4px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: var(--blue);">Sector ${idx + 1}</div>
            <div class="output-grid">
              <div class="output-field">
                <div class="output-label">Start Date</div>
                <div class="output-value">${SectorBillingLogic.formatDate(s.start_date)}</div>
              </div>
              <div class="output-field">
                <div class="output-label">End Date</div>
                <div class="output-value">${SectorBillingLogic.formatDate(s.end_date)}</div>
              </div>
              <div class="output-field">
                <div class="output-label">Days</div>
                <div class="output-value">${s.days || 0}</div>
              </div>
              <div class="output-field">
                <div class="output-label">Start Reading</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(s.start_reading)} L</div>
              </div>
              <div class="output-field">
                <div class="output-label">End Reading</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(s.end_reading)} L</div>
              </div>
              <div class="output-field">
                <div class="output-label">Total Usage</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(s.total_usage)} L</div>
              </div>
              <div class="output-field">
                <div class="output-label">Daily Usage</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(s.daily_usage)} L/day</div>
              </div>
            </div>
          </div>`;
        });
        
        html += `</div></div>`;
      } else {
        // Single sector or OPEN period - show period summary only
        html += `<div class="output-section">
          <div class="output-header" onclick="this.parentElement.classList.toggle('collapsed')">Sector Information</div>
          <div class="output-content">
            <div class="output-grid">
              <div class="output-field">
                <div class="output-label">Start Date</div>
                <div class="output-value">${SectorBillingLogic.formatDate(sector.start_date)}</div>
              </div>
              <div class="output-field">
                <div class="output-label">End Date</div>
                <div class="output-value">${sector.end_date ? SectorBillingLogic.formatDate(sector.end_date) : '—'}</div>
              </div>
              <div class="output-field">
                <div class="output-label">Days</div>
                <div class="output-value">${sector.days || 0}</div>
              </div>
              <div class="output-field">
                <div class="output-label">Status</div>
                <div class="output-value"><span class="badge ${sector.status}">${sector.status}</span></div>
              </div>
            </div>
          </div>
        </div>`;
      }
      
      // Usage Calculation
      if (sector.total_usage !== null && sector.total_usage !== undefined) {
        html += `<div class="output-section">
          <div class="output-header usage" onclick="this.parentElement.classList.toggle('collapsed')">Usage Calculation</div>
          <div class="output-content">
            <div class="output-grid">
              <div class="output-field">
                <div class="output-label">Total Usage</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(sector.total_usage)} L</div>
              </div>
              <div class="output-field">
                <div class="output-label">Daily Usage</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(sector.daily_usage)} L/day</div>
              </div>
              <div class="output-field">
                <div class="output-label">Start Reading</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(sector.start_reading)} L</div>
              </div>
              <div class="output-field">
                <div class="output-label">End Reading</div>
                <div class="output-value">${SectorBillingLogic.formatNumber(sector.end_reading)} L</div>
              </div>
            </div>
          </div>
        </div>`;
        
        // Tier Charges
        if (sector.tier_items && sector.tier_items.length > 0) {
          html += `<div class="output-section collapsed">
            <div class="output-header tier" onclick="this.parentElement.classList.toggle('collapsed')">Tier Charges</div>
            <div class="output-content">
              <div class="output-grid">`;
          
          sector.tier_items.forEach(item => {
            html += `<div class="output-field">
              <div class="output-label">Tier ${item.prev}–${item.max === Infinity ? '∞' : item.max} L</div>
              <div class="output-value">${item.used.toFixed(0)} L @ R${item.rate}/kL = R${item.cost.toFixed(2)}</div>
            </div>`;
          });
          
          html += `</div></div></div>`;
        }
        
        // Cost Summary
        const dailyCost = sector.days > 0 ? sector.tier_cost / sector.days : 0;
        html += `<div class="output-section">
          <div class="output-header cost" onclick="this.parentElement.classList.toggle('collapsed')">Cost Summary</div>
          <div class="output-content">
            <div class="output-grid">
              <div class="output-field">
                <div class="output-label">Total Cost</div>
                <div class="output-value total">R ${sector.tier_cost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")}</div>
              </div>
              <div class="output-field">
                <div class="output-label">Average Daily Cost</div>
                <div class="output-value">R ${dailyCost.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ")} / day</div>
              </div>
            </div>
          </div>
        </div>`;
      }
      
      container.innerHTML = html;
    },
    
    save_revision: function(action, details) {
      revisionNumber++;
      const revision = {
        number: revisionNumber,
        timestamp: new Date().toISOString(),
        action: action,
        details: details
      };
      revisions.push(revision);
      this.render_revision_history();
    },
    
    render_revision_history: function() {
      const container = document.getElementById('sector_revision_history');
      if (!container) return;
      
      container.innerHTML = '';
      revisions.slice().reverse().forEach(rev => {
        const div = document.createElement('div');
        div.className = 'revision-item';
        div.innerHTML = `
          <span class="revision-number">Rev ${rev.number}</span>
          <span class="revision-timestamp">${new Date(rev.timestamp).toLocaleString()}</span>
          <span class="revision-action">${rev.action}</span>
          <div>${rev.details}</div>
        `;
        container.appendChild(div);
      });
    },
    
    clear_revision_history: function() {
      revisions = [];
      revisionNumber = 0;
      this.render_revision_history();
    }
  };
})();

// Global wrapper functions for sector operations
// Global function to close active period (called from HTML button)
window.closeActivePeriod = function() {
  const active = SectorBillingLogic.getActiveSector();
  if (active !== null) {
    SectorBillingLogic.closeSector(active);
    SectorBillingUI.render();
  }
};

// Global function - must be accessible from HTML onclick
// Prevent multiple simultaneous calls
let isAddingReading = false;

window.add_sector_reading = function() {
  if (isAddingReading) {
    console.log('add_sector_reading already in progress, ignoring call');
    return;
  }
  
  try {
    isAddingReading = true;
    console.log('add_sector_reading called');
    // Get date picker (required only for Period 1 creation to set start_date)
    const datePicker = document.getElementById('sector_date_picker');
    if (!datePicker) {
      console.error('Date picker not found');
      alert('Date picker not found. Please refresh the page.');
      isAddingReading = false;
      return;
    }
    
    // Get sectors and active sector
    let sectors = SectorBillingLogic.getSectors();
    let active = SectorBillingLogic.getActiveSector();
    console.log('Sectors:', sectors.length, 'Active:', active);
    
    // If no sectors exist, create Period 1 - date picker is REQUIRED for start_date
    if (sectors.length === 0) {
      if (!datePicker.value) {
        console.log('No date selected for Period 1');
        alert('Please select a start date first to create Period 1.');
        isAddingReading = false;
        return;
      }
      
      console.log('Creating Period 1');
      // Create date from date picker value for Period 1 start_date
      let selectedDate;
      let dateStr;
      try {
        selectedDate = new Date(datePicker.value);
        if (isNaN(selectedDate.getTime())) {
          console.error('Invalid date:', datePicker.value);
          alert('Invalid date selected. Please select a valid date.');
          isAddingReading = false;
          return;
        }
        selectedDate.setHours(12, 0, 0, 0);
        
        if (typeof SectorBillingLogic === 'undefined' || !SectorBillingLogic.iso) {
          console.error('SectorBillingLogic.iso is not defined!');
          alert('Error: SectorBillingLogic.iso is not defined. Please refresh the page.');
          isAddingReading = false;
          return;
        }
        
        dateStr = SectorBillingLogic.iso(selectedDate);
        
        // Update current_date when user selects a date
        if (typeof SectorBillingLogic.setCurrentDate === 'function') {
          SectorBillingLogic.setCurrentDate(selectedDate);
        }
      } catch (e) {
        console.error('Error processing date:', e);
        alert('Error processing date: ' + e.message);
        isAddingReading = false;
        return;
      }
      
      // Create Period 1 with selected date as start_date, but first reading has date: null (user selects in row)
      sectors.push({
        sector_id: 1,
        start_date: new Date(selectedDate),
        end_date: null, // No end date for OPEN sector
        start_reading: 0, // Will be set when first reading value is entered
        end_reading: 0,
        total_usage: 0,
        daily_usage: 0,
        days: 0,
        status: 'OPEN',
        readings: [{ date: null, value: null }], // User will select date and enter value in table row
        tier_cost: 0,
        tier_items: []
      });
      
      SectorBillingLogic.setSectors(sectors);
      SectorBillingLogic.setActiveSector(0);
      SectorBillingUI.save_revision('Period 1 Created', `Period 1 started on ${dateStr}. Select date and enter reading value in table.`);
      console.log('Period 1 created, rendering...');
      SectorBillingUI.render();
      console.log('Period 1 render complete, returning');
      isAddingReading = false; // Reset flag before returning
      return;
    }
    
    // NOTE: Auto-close logic when date >30 days is handled in the date input's onchange handler
    // When user selects a date in a row that is >30 days from period start, the period will auto-close
    // No restrictions on adding readings - same as Period to Period mode
    
    // Check if active sector is CLOSED - if so, create new sector
    if (active !== null && sectors[active] && sectors[active].status === 'CLOSED') {
      // Create new sector starting from closing date + 1 day (not from selected date)
      const closedSector = sectors[active];
      const newSectorStartDate = new Date(closedSector.end_date);
      newSectorStartDate.setDate(newSectorStartDate.getDate() + 1); // Day after closing date
      newSectorStartDate.setHours(12, 0, 0, 0);
      
      // End reading from closed sector becomes start reading for new sector
      const previousEndReading = closedSector.end_reading !== null && closedSector.end_reading !== undefined 
        ? closedSector.end_reading 
        : 0;
      
      // For Period 2+, do NOT auto-insert date - user will select date when adding reading
      // Only Period 1 auto-inserts the date
      const newSectorId = sectors.length + 1;
      sectors.push({
        sector_id: newSectorId,
        start_date: newSectorStartDate,
        end_date: null, // No end date for OPEN sector
        start_reading: previousEndReading, // Set opening reading to previous period's closing reading
        end_reading: 0,
        total_usage: 0,
        daily_usage: 0,
        days: 0,
        status: 'OPEN',
        readings: [], // No readings auto-inserted for Period 2+ - user will add readings with date selector
        tier_cost: 0,
        tier_items: []
      });
      
      SectorBillingLogic.setSectors(sectors);
      SectorBillingLogic.setActiveSector(sectors.length - 1);
      active = sectors.length - 1;
      
      const newStartDateStr = SectorBillingLogic.iso(newSectorStartDate);
      SectorBillingUI.save_revision('New Sector Created', `Sector ${newSectorId} started on ${newStartDateStr} (day after previous period closed)`);
      SectorBillingUI.render();
      return;
    }
    
    // Ensure we have an active sector
    if (active === null) {
      alert('No active period. Please ensure Period 1 is open.');
      return;
    } else if (active === null) {
      // Sectors exist but none is active - check if last sector is CLOSED
      const lastSector = sectors[sectors.length - 1];
      if (lastSector && lastSector.status === 'CLOSED') {
        // Create new sector starting from closing date + 1 day
        const newSectorStartDate = new Date(lastSector.end_date);
        newSectorStartDate.setDate(newSectorStartDate.getDate() + 1); // Day after closing date
        newSectorStartDate.setHours(12, 0, 0, 0);
        
        // End reading from closed sector becomes start reading for new sector
        const previousEndReading = lastSector.end_reading !== null && lastSector.end_reading !== undefined 
          ? lastSector.end_reading 
          : 0;
        
        // For Period 2+, do NOT auto-insert date - user will select date when adding reading
        // Only Period 1 auto-inserts the date
        const newSectorId = sectors.length + 1;
        sectors.push({
          sector_id: newSectorId,
          start_date: newSectorStartDate,
          end_date: null, // No end date for OPEN sector
          start_reading: previousEndReading, // Set opening reading to previous period's closing reading
          end_reading: 0,
          total_usage: 0,
          daily_usage: 0,
          days: 0,
          status: 'OPEN',
          readings: [], // No readings auto-inserted for Period 2+ - user will add readings with date selector
          tier_cost: 0,
          tier_items: []
        });
        
        SectorBillingLogic.setSectors(sectors);
        SectorBillingLogic.setActiveSector(sectors.length - 1);
        active = sectors.length - 1;
        
        const newStartDateStr = SectorBillingLogic.iso(newSectorStartDate);
        SectorBillingUI.save_revision('New Sector Created', `Sector ${newSectorId} started on ${newStartDateStr} (day after previous period closed)`);
        
        // New sector created with one reading - don't add another reading, just render
        SectorBillingUI.render();
        isAddingReading = false; // Reset flag before returning
        return;
      } else {
        // Activate the last sector if it's OPEN
        active = sectors.length - 1;
        SectorBillingLogic.setActiveSector(active);
      }
    }
    
    // Get the active sector
    sectors = SectorBillingLogic.getSectors();
    const sector = sectors[active];
    
    if (!sector) {
      alert('Error: Could not find active sector');
      isAddingReading = false; // Reset flag before returning
      return;
    }
    
    // Initialize readings array if it doesn't exist
    if (!sector.readings) {
      sector.readings = [];
    }
    
    // Check if the last reading (excluding opening reading) has a value
    // Opening reading is filtered out from display, so we need to check displayed readings
    const openingReadingDate = sector.start_date ? SectorBillingLogic.iso(sector.start_date) : null;
    const openingReadingValue = sector.start_reading !== null && sector.start_reading !== undefined ? sector.start_reading : null;
    
    // Filter out opening reading to get displayed readings
    const displayedReadings = sector.readings.filter(reading => {
      // Exclude readings before period start date
      if (sector.start_date && reading.date) {
        const readingDate = new Date(reading.date);
        const startDate = new Date(sector.start_date);
        if (readingDate < startDate) {
          return false; // Reading is before period start - exclude it
        }
      }
      
      if (openingReadingDate && openingReadingValue !== null) {
        const readingDate = reading.date ? SectorBillingLogic.iso(new Date(reading.date)) : null;
        const readingValue = reading.value !== null && reading.value !== undefined ? reading.value : null;
        // Exclude if it matches both opening date and opening value
        if (readingDate === openingReadingDate && readingValue === openingReadingValue) {
          return false; // This is the opening reading - exclude from check
        }
      }
      return true; // Keep all other readings
    });
    
    // Check if the last displayed reading has a value - if not, prevent adding a new reading
    // BUT: If there are no displayed readings at all, allow adding the first one
    if (displayedReadings.length > 0) {
      const lastReading = displayedReadings[displayedReadings.length - 1];
      if (lastReading.value === null || lastReading.value === undefined) {
        alert('Please enter a reading value for the current row before adding a new reading.');
        isAddingReading = false; // Reset flag before returning
        return;
      }
    }
    // If displayedReadings.length === 0, we allow adding the first reading
    
    // No need to check for duplicate dates here - readings are created with date: null
    // User will select the date in the row, and duplicate check happens in dateInput.onchange
    
    console.log('Adding reading to sector:', active);
    // Add a new reading row with empty date - user will select date in the row
    sector.readings.push({ date: null, value: null });
    console.log('Reading added, total readings:', sector.readings.length);
    
    // Update sectors
    SectorBillingLogic.setSectors(sectors);
    
    SectorBillingUI.save_revision('Reading Row Added', 'New reading row added. Select date and enter value.');
    
    // Update date picker to next available date (day after the newly added reading)
    SectorBillingUI.updateDatePickerDefault();
    
    // Recalculate sector days and daily_usage if there are readings with values
    const readingsWithValues = sector.readings.filter(r => r.date && r.value !== null && r.value !== undefined);
    if (readingsWithValues.length > 0) {
      const sortedReadings = [...readingsWithValues].sort((a, b) => new Date(a.date) - new Date(b.date));
      const lastReading = sortedReadings[sortedReadings.length - 1];
      
      if (sector.start_date && lastReading.date) {
        const startDate = new Date(sector.start_date);
        startDate.setHours(12, 0, 0, 0);
        const endDate = new Date(lastReading.date);
        endDate.setHours(12, 0, 0, 0);
        sector.days = SectorBillingLogic.daysBetween(startDate, endDate);
        
        // For Period 1: first reading value becomes start_reading
        // For Period 2+: start_reading is already set from previous period's end_reading
        if (sector.start_reading === 0 || sector.start_reading === null || sector.start_reading === undefined) {
          // Period 1: first reading value becomes the start_reading
          sector.start_reading = sortedReadings[0].value;
        }
        // For Period 2+, start_reading is already set correctly from previous period
        
        sector.end_reading = lastReading.value;
        sector.total_usage = sector.end_reading - sector.start_reading;
        
        if (sector.days > 0) {
          sector.daily_usage = sector.total_usage / sector.days;
        } else {
          sector.daily_usage = 0;
        }
      }
    }
    
    console.log('Rendering...');
    SectorBillingUI.render();
    console.log('Render complete');
  } catch (error) {
    console.error('ERROR in add_sector_reading:', error);
    console.error('Error stack:', error.stack);
    alert('Error adding reading: ' + error.message);
  } finally {
    isAddingReading = false;
    console.log('add_sector_reading complete, flag reset');
  }
};

// Global function - must be accessible from HTML onclick
// Expose calculate function globally for onclick handler
window.calculate = calculate;

window.calculate_sector = function() {
  try {
    // MANDATORY: Check if template is selected before calculation
    if (typeof currentTemplateTiers === 'undefined' || currentTemplateTiers === null || currentTemplateTiers.length === 0) {
      const errorMsg = "Please select a tariff template before calculating. A template is required for cost calculations.";
      // Show error message in UI
      const errorDiv = document.getElementById('sector_calculate_error');
      if (errorDiv) {
        errorDiv.textContent = errorMsg;
        errorDiv.style.display = 'block';
        errorDiv.style.color = 'var(--red, #dc2626)';
        // Hide error after 5 seconds
        setTimeout(() => {
          errorDiv.style.display = 'none';
        }, 5000);
      }
      alert(errorMsg);
      return; // Stop calculation - template is mandatory
    }
    
    // Clear any previous error message
    const errorDiv = document.getElementById('sector_calculate_error');
    if (errorDiv) {
      errorDiv.style.display = 'none';
      errorDiv.textContent = '';
    }
    
    const active = SectorBillingLogic.getActiveSector();
    if (active === null) {
      alert('Please select a sector');
      return;
    }
    
    SectorBillingLogic.calculateSector(active);
    SectorBillingUI.save_revision('Sector Calculated', `Sector ${active + 1}`);
    SectorBillingUI.render();
    if (typeof window.updateBillPreview === 'function') {
      window.updateBillPreview();
    }
  } catch (error) {
    const errorMsg = 'Error calculating sector: ' + error.message;
    alert(errorMsg);
    const errorDiv = document.getElementById('sector_calculate_error');
    if (errorDiv) {
      errorDiv.textContent = errorMsg;
      errorDiv.style.display = 'block';
      errorDiv.style.color = 'var(--red, #dc2626)';
    }
    console.error(error);
  }
};

function copy_sector_output_to_clipboard() {
  // TODO: Implement copy functionality
  alert('Copy sector output functionality to be implemented');
}

function copy_all_sectors_to_clipboard() {
  // TODO: Implement copy functionality
  alert('Copy all sectors functionality to be implemented');
}

function copy_sector_input_history() {
  // TODO: Implement copy functionality
  alert('Copy sector input history functionality to be implemented');
}

function clear_sector_revision_history() {
  if (confirm('Clear all sector input history?')) {
    // Access private state through public API if needed, or reset through save_revision
    // For now, we'll need to add a clear method to SectorBillingUI
    if (typeof SectorBillingUI !== 'undefined' && SectorBillingUI.clear_revision_history) {
      SectorBillingUI.clear_revision_history();
    }
  }
}

/* ==================== INITIALIZATION ==================== */
// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    BillingEngineUI.init_revision_system();
    BillingEngineUI.init_context_menu();
    
    // Track initial load
    BillingEngineUI.save_revision('Page Loaded', 'Application initialized');
    
    // Initialize sector UI
    SectorBillingUI.renderMonthSelector();
});

// @END_PROTECTED_MODULE: period_calculation_core
// @END_PROTECTED_MODULE: SectorBillingLogic
// @END_PROTECTED_MODULE: SectorBillingUI