/**
 * Maldives Packages (MPK) - Frontend Wizard Engine
 * Handles 5-step wizard flow, location selection, guest counters,
 * resilient hotel filtering, interactive date picker with no auto-fill,
 * clean lead traveler form, compact booking review summary,
 * payment selection with badge, and confirmation view.
 *
 * Approved Prefix: MPK / mpk_ / mpk-
 * Strictly forbids TZ / tz prefix.
 */

(function () {
	'use strict';

	// Escape dynamic text before inserting into innerHTML (XSS hardening)
	var escDecoder = document.createElement('textarea');
	function escHtml(str) {
		if (str === null || str === undefined) return '';
		// Decode existing entities first (WP stores term names as "&amp;") to avoid double-escaping.
		// <textarea> content is RCDATA, so this never parses or executes markup.
		escDecoder.innerHTML = String(str);
		return escDecoder.value
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	// Default fallback dataset matching Mpackages/src/lib/booking-data.ts
	var DEFAULT_LOCATIONS = [
		{
			id: 'hulhumale',
			name: 'Hulhumale',
			tagline: 'Vibrant beachfront city',
			image: 'assets/images/loc-hulhumale.jpg',
			nights: 2
		},
		{
			id: 'maafushi',
			name: 'Maafushi Island',
			tagline: 'Local island charm',
			image: 'assets/images/loc-maafushi.jpg',
			nights: 2
		},
		{
			id: 'resort',
			name: 'Resort / Private Island',
			tagline: 'Pure overwater luxury',
			image: 'assets/images/loc-resort.jpg',
			nights: 3
		}
	];

	var DEFAULT_HOTELS = [
		{
			id: 'h-azure',
			name: 'Azure Bay Residence',
			location: 'hulhumale',
			stars: 4,
			review: 4.7,
			reviewLabel: 'Excellent',
			area: 'Hulhumale Beachfront',
			image: 'assets/images/hotel-3.jpg',
			amenities: ['Pool', 'Wifi', 'Spa', 'Restaurant'],
			rooms: [
				{ id: 'r1', name: 'Standard Room with Balcony', meal: 'Breakfast', price: 110, room_type: 'Balcony', bed_type: 'Double', amenities: ['Balcony', 'Double', 'Air Conditioning', 'Free Wifi'] },
				{ id: 'r2', name: 'Deluxe Sea View', meal: 'Breakfast & Dinner', price: 165, room_type: 'Sea View', bed_type: 'King', amenities: ['Sea View', 'King', 'Balcony', 'Mini Bar'] }
			]
		},
		{
			id: 'h-coral',
			name: 'Coral Sands Boutique',
			location: 'hulhumale',
			stars: 3,
			review: 4.4,
			reviewLabel: 'Very Good',
			area: 'Central Hulhumale',
			image: 'assets/images/hotel-1.jpg',
			amenities: ['Wifi', 'Restaurant', 'Airport Transfer'],
			rooms: [
				{ id: 'r1', name: 'Standard Twin', meal: 'Breakfast', price: 78, room_type: 'Island View', bed_type: 'Twin', amenities: ['Island View', 'Twin', 'Free Wifi'] },
				{ id: 'r2', name: 'Deluxe Double', meal: 'Breakfast & Dinner', price: 115, room_type: 'Balcony', bed_type: 'Double', amenities: ['Balcony', 'Double', 'City View'] }
			]
		},
		{
			id: 'h-pearl',
			name: 'Pearl Lagoon Inn',
			location: 'maafushi',
			stars: 3,
			review: 4.5,
			reviewLabel: 'Very Good',
			area: 'Maafushi Beach',
			image: 'assets/images/hotel-3.jpg',
			amenities: ['Wifi', 'Snorkeling', 'Restaurant'],
			rooms: [
				{ id: 'r1', name: 'Standard Room', meal: 'Breakfast', price: 85, room_type: 'Island View', bed_type: 'Single', amenities: ['Island View', 'Single', 'Double'] },
				{ id: 'r2', name: 'Sea View Deluxe', meal: 'All Inclusive', price: 175, room_type: 'Sea View', bed_type: 'Double', amenities: ['Sea View', 'Double', 'Balcony'] }
			]
		},
		{
			id: 'h-island',
			name: 'Island Breeze Hotel',
			location: 'maafushi',
			stars: 4,
			review: 4.6,
			reviewLabel: 'Excellent',
			area: 'Maafushi Sunset Side',
			image: 'assets/images/hotel-1.jpg',
			amenities: ['Pool', 'Wifi', 'Excursions'],
			rooms: [
				{ id: 'r1', name: 'Island View Room', meal: 'Breakfast', price: 120, room_type: 'Island View', bed_type: 'Double', amenities: ['Island View', 'Double', 'Sunset View'] },
				{ id: 'r2', name: 'Super Deluxe Suite', meal: 'Breakfast & Dinner', price: 220, room_type: 'Balcony', bed_type: 'Triple', amenities: ['Balcony', 'Triple', 'Suite', 'Living Area'] }
			]
		},
		{
			id: 'h-paradise',
			name: 'Paradise Overwater Resort',
			location: 'resort',
			menu_order: 1,
			stars: 5,
			review: 4.9,
			reviewLabel: 'Exceptional',
			area: 'South Atoll',
			image: 'assets/images/hotel-2.jpg',
			amenities: ['Overwater', 'Spa', 'All Inclusive', 'Infinity'],
			rooms: [
				{ id: 'r1', name: 'Beach Villa with Pool', meal: 'All Inclusive', price: 480, room_type: 'With Pool', bed_type: 'King', amenities: ['With Pool', 'King', 'Private Beach', 'Plunge Pool'] },
				{ id: 'r2', name: 'Water Villa', meal: 'All Inclusive', price: 720, room_type: 'Water Villa', bed_type: 'King', amenities: ['Water Villa', 'King', 'Lagoon Access', 'Sun Deck'] }
			]
		},
		{
			id: 'h-lagoon',
			name: 'Lagoon Crystal Resort',
			location: 'resort',
			menu_order: 2,
			stars: 5,
			review: 4.8,
			reviewLabel: 'Exceptional',
			area: 'South Atoll',
			image: 'assets/images/hotel-2.jpg',
			amenities: ['Overwater', 'Spa', 'Fine Dining'],
			rooms: [
				{ id: 'r1', name: 'Sunset Water Villa', meal: 'Breakfast & Dinner', price: 560, room_type: 'Water Villa', bed_type: 'King', amenities: ['Water Villa', 'Sea View', 'King'] },
				{ id: 'r2', name: 'Royal Suite with Pool', meal: 'All Inclusive', price: 890, room_type: 'With Pool', bed_type: 'King', amenities: ['With Pool', 'King', 'Private Infinity Pool', 'Butler Service'] }
			]
		}
	];

	// Localized data or fallback
	var rawData = window.MPK_INITIAL_DATA || {};
	var LOCATIONS = (rawData.locations && rawData.locations.length > 0) ? rawData.locations : DEFAULT_LOCATIONS;
	var HOTELS = (rawData.hotels && rawData.hotels.length > 0) ? rawData.hotels : DEFAULT_HOTELS;
	var PLUGIN_URL = rawData.plugin_url || '';

	// Ensure all hotel rooms have normalized room types and bed types
	function normalizeHotelsData(hotelsList) {
		if (!hotelsList || !Array.isArray(hotelsList)) return;
		for (var h = 0; h < hotelsList.length; h++) {
			var hotel = hotelsList[h];
			if (!hotel.rooms || !hotel.rooms.length) continue;

			for (var r = 0; r < hotel.rooms.length; r++) {
				var rm = hotel.rooms[r];
				if (!rm.amenities || !Array.isArray(rm.amenities)) {
					rm.amenities = [];
				}
				var roomText = ((rm.name || '') + ' ' + (rm.amenities.join(' '))).toLowerCase();

				// Ensure explicit room_type or infer from name/amenities
				if (!rm.room_type) {
					if (/water|overwater|lagoon\s*villa/i.test(roomText)) {
						rm.room_type = 'Water Villa';
					} else if (/pool|plunge/i.test(roomText)) {
						rm.room_type = 'With Pool';
					} else if (/sea|ocean|beach/i.test(roomText)) {
						rm.room_type = 'Sea View';
					} else if (/balcony|terrace|patio/i.test(roomText)) {
						rm.room_type = 'Balcony';
					} else {
						rm.room_type = 'Island View';
					}
				}

				// Ensure explicit bed_type or infer from name/amenities
				if (!rm.bed_type) {
					if (/triple|family|3\s*bed/i.test(roomText)) {
						rm.bed_type = 'Triple';
					} else if (/twin|2\s*single/i.test(roomText)) {
						rm.bed_type = 'Twin';
					} else if (/single|1\s*person|solo/i.test(roomText)) {
						rm.bed_type = 'Single';
					} else if (/king/i.test(roomText)) {
						rm.bed_type = 'King';
					} else {
						rm.bed_type = 'Double';
					}
				}
			}
		}
	}

	normalizeHotelsData(DEFAULT_HOTELS);
	normalizeHotelsData(HOTELS);

	function resolveImageUrl(img) {
		if (!img) return '';
		if (img.indexOf('http://') === 0 || img.indexOf('https://') === 0) {
			return img;
		}
		if (PLUGIN_URL) {
			var base = PLUGIN_URL;
			if (base.charAt(base.length - 1) !== '/') base += '/';
			return base + img.replace(/^\.?\//, '');
		}
		return img;
	}

	for (var li = 0; li < LOCATIONS.length; li++) {
		LOCATIONS[li].image = resolveImageUrl(LOCATIONS[li].image);
	}
	for (var hi = 0; hi < HOTELS.length; hi++) {
		HOTELS[hi].image = resolveImageUrl(HOTELS[hi].image);
	}

	// State Engine - Initial values 100% matched to reference React app
	var state = {
		step: 1,
		adults: 2,
		children: 0,
		infants: 0,
		rooms: 1,
		selectedLocations: [], // Starts empty, user picks locations
		selections: [],        // Starts empty, no room or date pre-selected
		form: {
			name: '',
			mobile: '',
			email: '',
			country: '',
			passport: '',
			request: '',
			fileName: ''
		},
		passportFile: null,    // Selected binary passport file object
		agreed: false,         // Unchecked by default
		paymentMethod: '',     // No default payment method selected
		filters: {
			search: '',
			stars: [],
			meals: [],
			beds: [],
			rooms: [],
			maxPrice: 1000
		},
		confirmationCode: ''
	};

	// Date Utilities
	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

	function formatDate(dStr, includeDay) {
		if (!dStr) return '';
		var parts = dStr.split('-');
		if (parts.length < 3) return dStr;
		var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		if (isNaN(d.getTime())) return dStr;
		var day = ('0' + d.getDate()).slice(-2);
		var month = MONTHS[d.getMonth()];
		var year = d.getFullYear();
		if (includeDay) {
			return DAYS[d.getDay()] + ', ' + day + ' ' + month + ' ' + year;
		}
		return day + ' ' + month + ' ' + year;
	}

	function diffDays(startStr, endStr) {
		if (!startStr || !endStr) return 0;
		var p1 = startStr.split('-');
		var p2 = endStr.split('-');
		var d1 = new Date(parseInt(p1[0], 10), parseInt(p1[1], 10) - 1, parseInt(p1[2], 10));
		var d2 = new Date(parseInt(p2[0], 10), parseInt(p2[1], 10) - 1, parseInt(p2[2], 10));
		var ms = d2.getTime() - d1.getTime();
		return Math.max(0, Math.round(ms / (1000 * 60 * 60 * 24)));
	}

	function addDays(dStr, numDays) {
		if (!dStr) return '';
		var parts = dStr.split('-');
		var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		d.setDate(d.getDate() + numDays);
		var y = d.getFullYear();
		var m = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return y + '-' + m + '-' + day;
	}

	function getTomorrowStr() {
		var d = new Date();
		d.setDate(d.getDate() + 1);
		var y = d.getFullYear();
		var m = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return y + '-' + m + '-' + day;
	}

	function generateConfirmationCode() {
		var now = new Date();
		var y = now.getFullYear();
		var m = ('0' + (now.getMonth() + 1)).slice(-2);
		var d = ('0' + now.getDate()).slice(-2);
		var stamp = '' + y + m + d;
		var rand = Math.random().toString(36).substring(2, 7).toUpperCase();
		return 'MPK-' + stamp + '-' + rand;
	}

	// Room & Bed Filter Matching Engine
	function roomMatchesBed(room, bedType) {
		if (!bedType) return true;
		var bt = bedType.toLowerCase();
		var rBed = (room.bed_type || '').toLowerCase();
		var rName = (room.name || '').toLowerCase();
		var rAmenities = (room.amenities || []).map(function (a) { return ('' + a).toLowerCase(); });

		if (bt === 'single') {
			return rBed === 'single' || rAmenities.indexOf('single') !== -1 || /\bsingle\b/i.test(rName);
		}
		if (bt === 'double') {
			return rBed === 'double' || rBed === 'king' || rAmenities.indexOf('double') !== -1 || rAmenities.indexOf('king') !== -1 || /\b(double|king)\b/i.test(rName);
		}
		if (bt === 'twin') {
			return rBed === 'twin' || rAmenities.indexOf('twin') !== -1 || /\btwin\b/i.test(rName);
		}
		if (bt === 'triple') {
			return rBed === 'triple' || rAmenities.indexOf('triple') !== -1 || /\btriple\b/i.test(rName) || /\b(family|3\s*bed)\b/i.test(rName);
		}
		if (bt === 'king') {
			return rBed === 'king' || rAmenities.indexOf('king') !== -1 || /\b(king|master)\b/i.test(rName);
		}
		return rBed === bt || rAmenities.indexOf(bt) !== -1;
	}

	function roomMatchesRoomType(room, roomType) {
		if (!roomType) return true;
		var rt = roomType.toLowerCase();
		var rType = (room.room_type || '').toLowerCase();
		var rName = (room.name || '').toLowerCase();
		var rAmenities = (room.amenities || []).map(function (a) { return ('' + a).toLowerCase(); });

		if (rt === 'balcony') {
			return rType === 'balcony' || rAmenities.indexOf('balcony') !== -1 || /\b(balcony|terrace|patio)\b/i.test(rName);
		}
		if (rt === 'sea view') {
			return rType === 'sea view' || rAmenities.indexOf('sea view') !== -1 || /\b(sea|ocean)\s*view\b/i.test(rName);
		}
		if (rt === 'island view') {
			return rType === 'island view' || rAmenities.indexOf('island view') !== -1 || /\b(island|garden|city)\s*view\b/i.test(rName);
		}
		if (rt === 'with pool') {
			return rType === 'with pool' || rAmenities.indexOf('with pool') !== -1 || /\b(pool|plunge)\b/i.test(rName);
		}
		if (rt === 'water villa') {
			return rType === 'water villa' || rAmenities.indexOf('water villa') !== -1 || /\b(water|overwater)\s*villa\b/i.test(rName);
		}
		return rType === rt || rAmenities.indexOf(rt) !== -1;
	}

	function isRoomMatchingFilters(room, filters) {
		if (!room) return false;
		if (!filters) return true;

		// 1. Max Price filter
		if (typeof filters.maxPrice === 'number') {
			if (roomRate(room) > filters.maxPrice) return false;
		}

		// 2. Meal filter (if any selected, room.meal must match one)
		if (filters.meals && filters.meals.length > 0) {
			if (filters.meals.indexOf(room.meal) === -1) return false;
		}

		// 3. Bed Type filter (if any selected, room must match at least one)
		if (filters.beds && filters.beds.length > 0) {
			var hasMatchingBed = false;
			for (var b = 0; b < filters.beds.length; b++) {
				if (roomMatchesBed(room, filters.beds[b])) {
					hasMatchingBed = true;
					break;
				}
			}
			if (!hasMatchingBed) return false;
		}

		// 4. Room Type filter (if any selected, room must match at least one)
		if (filters.rooms && filters.rooms.length > 0) {
			var hasMatchingRoomType = false;
			for (var r = 0; r < filters.rooms.length; r++) {
				if (roomMatchesRoomType(room, filters.rooms[r])) {
					hasMatchingRoomType = true;
					break;
				}
			}
			if (!hasMatchingRoomType) return false;
		}

		return true;
	}

	// Pricing settings from admin (safe defaults)
	function getPricingRules() {
		var ps = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.settings) ? window.MPK_INITIAL_DATA.settings : {};
		function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }
		return {
			taxRate: Math.max(0, num(ps.tax_rate, 0.08)),
			extras: Math.max(0, num(ps.extras, 45)),
			service: Math.max(0, num(ps.service_fee, 25)),
			childDiscount: Math.min(100, Math.max(0, num(ps.child_discount_pct, 30))),
			markup: Math.min(100, Math.max(0, num(ps.markup_pct, 0)))
		};
	}

	// Nightly rate customers see and pay (admin room rate + package markup)
	function roomRate(room) {
		if (!room) return 0;
		var p = Math.max(0, parseFloat(room.price) || 0);
		return Math.round(p * (1 + getPricingRules().markup / 100) * 100) / 100;
	}

	function fmtMoney(n) {
		return (Math.round(n * 100) / 100).toFixed(2);
	}

	// Real-time pricing - keep in sync with build_trip_from_selections() in class-mpk-ajax-handler.php
	// Rooms (2 adults incl.) + extra adults + children (infants free) -> tax -> + extras + service fee
	function calcPricing() {
		var rules = getPricingRules();
		var rooms = Math.max(1, state.rooms || 1);
		var adults = Math.max(1, state.adults || 1);
		var children = Math.max(0, state.children || 0);
		var extraAdults = Math.max(0, adults - (2 * rooms));

		var roomCost = 0, extraAdultCost = 0, childCost = 0, totalNights = 0;
		for (var i = 0; i < state.selections.length; i++) {
			var sel = state.selections[i];
			var hotel = findHotel(sel.hotelId);
			var room = hotel ? findRoom(hotel, sel.roomId) : null;
			var nights = (sel.checkIn && sel.checkOut) ? diffDays(sel.checkIn, sel.checkOut) : 0;
			totalNights += nights;
			if (room && nights > 0) {
				var rate = roomRate(room);
				var share = rate / 2;
				roomCost += rate * nights * rooms;
				extraAdultCost += extraAdults * share * nights;
				childCost += children * share * (1 - rules.childDiscount / 100) * nights;
			}
		}

		var subtotal = roomCost + extraAdultCost + childCost;
		if (subtotal <= 0) {
			return { roomCost: 0, extraAdultCost: 0, childCost: 0, subtotal: 0, extras: 0, tax: 0, service: 0, total: 0, totalNights: totalNights, rooms: rooms, extraAdults: extraAdults };
		}

		var tax = subtotal * rules.taxRate;
		var total = subtotal + tax + rules.extras + rules.service;

		return {
			roomCost: roomCost,
			extraAdultCost: extraAdultCost,
			childCost: childCost,
			subtotal: subtotal,
			extras: rules.extras,
			tax: tax,
			service: rules.service,
			total: Math.round(total * 100) / 100,
			totalNights: totalNights,
			rooms: rooms,
			extraAdults: extraAdults
		};
	}

	function findHotel(hotelId) {
		for (var i = 0; i < HOTELS.length; i++) {
			if (HOTELS[i].id === hotelId) return HOTELS[i];
		}
		return null;
	}

	function findRoom(hotel, roomId) {
		if (!hotel || !hotel.rooms) return null;
		for (var i = 0; i < hotel.rooms.length; i++) {
			if (hotel.rooms[i].id === roomId) return hotel.rooms[i];
		}
		return null;
	}

	function findLocation(locId) {
		for (var i = 0; i < LOCATIONS.length; i++) {
			if (LOCATIONS[i].id === locId) return LOCATIONS[i];
		}
		return null;
	}

	function getSelection(hotelId, roomId) {
		for (var i = 0; i < state.selections.length; i++) {
			var s = state.selections[i];
			if (s.hotelId === hotelId && s.roomId === roomId) return s;
		}
		return null;
	}

	function getEarliestAllowedDate(hotelId, roomId) {
		var latestCheckOut = '';
		for (var i = 0; i < state.selections.length; i++) {
			var s = state.selections[i];
			if (s.hotelId === hotelId && s.roomId === roomId) continue;
			if (s.checkOut && s.checkOut > latestCheckOut) {
				latestCheckOut = s.checkOut;
			}
		}
		return latestCheckOut || getTomorrowStr();
	}

	// Step Validation matching Route logic
	function canContinue() {
		if (state.step === 1) {
			return state.selectedLocations.length > 0;
		}
		if (state.step === 2) {
			if (state.selections.length === 0) return false;
			for (var i = 0; i < state.selections.length; i++) {
				var s = state.selections[i];
				if (!s.checkIn || !s.checkOut) return false;
				if (diffDays(s.checkIn, s.checkOut) <= 0) return false;
			}
			return true;
		}
		if (state.step === 3) {
			return (
				state.agreed &&
				state.form.name.trim().length > 0 &&
				state.form.email.trim().length > 0
			);
		}
		if (state.step === 4) {
			return !!state.paymentMethod;
		}
		return true;
	}

	var isSubmitting = false;

	function updateNavState() {
		var btnBack = document.querySelector('.mpk-btn-back');
		var btnNext = document.querySelector('.mpk-btn-next');
		var nextLabel = document.getElementById('mpk-btn-next-label');
		var hint = document.getElementById('mpk-validation-hint');

		if (!btnBack || !btnNext) return;

		// Back button visibility
		if (state.step > 1 && state.step < 5) {
			btnBack.style.display = 'inline-flex';
			btnBack.style.visibility = 'visible';
		} else {
			btnBack.style.visibility = 'hidden';
			btnBack.style.display = 'inline-flex';
		}

		// Next button & labels
		if (state.step === 5) {
			btnNext.style.display = 'none';
			if (hint) hint.textContent = '';
			return;
		} else {
			btnNext.style.display = 'inline-flex';
		}

		if (nextLabel) {
			if (state.step === 3) {
				nextLabel.textContent = 'Confirm Booking';
			} else if (state.step === 4) {
				nextLabel.textContent = isSubmitting ? 'Submitting...' : 'Complete Booking';
			} else {
				nextLabel.textContent = 'Continue';
			}
		}

		var ok = canContinue();
		btnNext.disabled = !ok || isSubmitting;

		if (hint && !isSubmitting) {
			if (ok) {
				hint.textContent = '';
				hint.style.color = '';
			} else {
				if (state.step === 1) hint.textContent = 'Select at least one location';
				else if (state.step === 2) hint.textContent = 'Select a room and set check-in / check-out';
				else if (state.step === 3) hint.textContent = 'Complete your details and agree to the terms';
				else if (state.step === 4) hint.textContent = 'Choose a payment method';
			}
		}
	}

	function setStep(newStep) {
		if (newStep < 1 || newStep > 5) return;
		state.step = newStep;

		// Update step panes
		var panes = document.querySelectorAll('.mpk-step-pane');
		for (var i = 0; i < panes.length; i++) {
			var p = panes[i];
			var pStep = parseInt(p.getAttribute('data-step'), 10);
			if (pStep === state.step) {
				p.classList.add('active');
			} else {
				p.classList.remove('active');
			}
		}

		// Update Stepper track columns
		var stepColumns = document.querySelectorAll('.mpk-step-column');
		for (var j = 0; j < stepColumns.length; j++) {
			var col = stepColumns[j];
			var sNum = parseInt(col.getAttribute('data-step'), 10);
			var circle = col.querySelector('.mpk-step-circle');
			var connectorFill = col.querySelector('.mpk-step-connector-fill');

			if (sNum === state.step) {
				col.classList.add('active');
				col.classList.remove('completed');
				if (circle) circle.textContent = sNum;
				if (connectorFill) connectorFill.style.width = '0%';
			} else if (sNum < state.step) {
				col.classList.remove('active');
				col.classList.add('completed');
				if (circle) {
					circle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
				}
				if (connectorFill) connectorFill.style.width = '100%';
			} else {
				col.classList.remove('active');
				col.classList.remove('completed');
				if (circle) circle.textContent = sNum;
				if (connectorFill) connectorFill.style.width = '0%';
			}
		}

		// Render active view
		if (state.step === 2) {
			renderHotels();
		} else if (state.step === 3) {
			renderReview();
			var dropInfo = document.getElementById('mpk-passport-drop-fileinfo');
			var dropLabel = document.getElementById('mpk-passport-drop-label');
			var dropFilename = document.getElementById('mpk-passport-filename');
			if (state.passportFile || (state.form && state.form.fileName)) {
				var fName = state.passportFile ? state.passportFile.name : state.form.fileName;
				var fSize = state.passportFile ? ' (' + (state.passportFile.size / (1024 * 1024)).toFixed(2) + ' MB)' : '';
				if (dropFilename) dropFilename.textContent = fName + fSize;
				if (dropLabel) dropLabel.style.display = 'none';
				if (dropInfo) dropInfo.style.display = 'flex';
			}
		} else if (state.step === 4) {
			var pCards = document.querySelectorAll('.mpk-payment-card');
			for (var pc = 0; pc < pCards.length; pc++) {
				if (pCards[pc].getAttribute('data-payment-method') === state.paymentMethod) {
					pCards[pc].classList.add('selected');
				} else {
					pCards[pc].classList.remove('selected');
				}
			}
		} else if (state.step === 5) {
			renderConfirmation();
		}

		updateNavState();

		var container = document.getElementById('mpk-booking-app');
		if (container) {
			container.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	// STEP 1: Handlers
	function initStep1() {
		var cards = document.querySelectorAll('.mpk-location-card');
		for (var i = 0; i < cards.length; i++) {
			var cardLocId = cards[i].getAttribute('data-location-id');
			if (state.selectedLocations.indexOf(cardLocId) !== -1) {
				cards[i].classList.add('selected');
			}

			cards[i].addEventListener('click', function () {
				var locId = this.getAttribute('data-location-id');
				var idx = state.selectedLocations.indexOf(locId);
				if (idx > -1) {
					state.selectedLocations.splice(idx, 1);
					this.classList.remove('selected');
				} else {
					state.selectedLocations.push(locId);
					this.classList.add('selected');
				}
				updateNavState();
				renderHotels();
			});
		}

		var minusBtns = document.querySelectorAll('.mpk-btn-qty-minus');
		var plusBtns = document.querySelectorAll('.mpk-btn-qty-plus');

		// Occupancy limits per room (from server; infants not counted as guests)
		var occ = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.occupancy) ? window.MPK_INITIAL_DATA.occupancy : {};
		var MAX_ADULTS = parseInt(occ.max_adults, 10) || 3;
		var MAX_GUESTS = parseInt(occ.max_guests, 10) || 4;
		var MAX_INFANTS = (occ.max_infants !== undefined) ? (parseInt(occ.max_infants, 10) || 0) : 2;
		var MAX_ROOMS = 10;

		function fitsRooms(a, c, i, r) {
			return r >= 1 && r <= a && a <= MAX_ADULTS * r && (a + c) <= MAX_GUESTS * r && i <= MAX_INFANTS * r;
		}

		function updateCounters() {
			var adultsEl = document.getElementById('mpk-val-adults');
			var childrenEl = document.getElementById('mpk-val-children');
			var infantsEl = document.getElementById('mpk-val-infants');
			var roomsEl = document.getElementById('mpk-val-rooms');
			var totalEl = document.getElementById('mpk-total-travelers');

			if (adultsEl) adultsEl.textContent = state.adults;
			if (childrenEl) childrenEl.textContent = state.children;
			if (infantsEl) infantsEl.textContent = state.infants;
			if (roomsEl) roomsEl.textContent = state.rooms;
			if (totalEl) totalEl.textContent = state.adults + state.children + state.infants;

			for (var m = 0; m < minusBtns.length; m++) {
				var btn = minusBtns[m];
				var tgt = btn.getAttribute('data-target');
				if (tgt === 'adults') btn.disabled = state.adults <= 1 || !fitsRooms(state.adults - 1, state.children, state.infants, Math.min(state.rooms, state.adults - 1));
				if (tgt === 'children') btn.disabled = state.children <= 0;
				if (tgt === 'infants') btn.disabled = state.infants <= 0;
				if (tgt === 'rooms') btn.disabled = state.rooms <= 1 || !fitsRooms(state.adults, state.children, state.infants, state.rooms - 1);
			}

			var a = state.adults, c = state.children, i = state.infants, r = state.rooms;
			for (var pl = 0; pl < plusBtns.length; pl++) {
				var pb = plusBtns[pl];
				var pt = pb.getAttribute('data-target');
				if (pt === 'adults') pb.disabled = a >= 20 || !fitsRooms(a + 1, c, i, r);
				if (pt === 'children') pb.disabled = c >= 20 || !fitsRooms(a, c + 1, i, r);
				if (pt === 'infants') pb.disabled = i >= 10 || !fitsRooms(a, c, i + 1, r);
				if (pt === 'rooms') pb.disabled = r >= MAX_ROOMS || !fitsRooms(a, c, i, r + 1);
			}

			var occHint = document.getElementById('mpk-occupancy-hint');
			if (occHint) {
				occHint.textContent = 'Max ' + MAX_ADULTS + ' adults / ' + MAX_GUESTS + ' guests (excl. infants) per room. Add a room for more guests.';
			}
		}

		for (var p = 0; p < plusBtns.length; p++) {
			plusBtns[p].addEventListener('click', function () {
				var tgt = this.getAttribute('data-target');
				var na = state.adults + (tgt === 'adults' ? 1 : 0);
				var nc = state.children + (tgt === 'children' ? 1 : 0);
				var ni = state.infants + (tgt === 'infants' ? 1 : 0);
				var nr = state.rooms + (tgt === 'rooms' ? 1 : 0);
				if (!tgt || !fitsRooms(na, nc, ni, nr)) return;
				state.adults = na; state.children = nc; state.infants = ni; state.rooms = nr;
				updateCounters();
				if (state.step === 3) renderReview();
			});
		}

		for (var m = 0; m < minusBtns.length; m++) {
			minusBtns[m].addEventListener('click', function () {
				var tgt = this.getAttribute('data-target');
				if (!tgt) return;
				// Reducing adults below rooms also reduces rooms (each room needs an adult)
				if (tgt === 'adults' && state.adults > 1) {
					var la = state.adults - 1, lr = Math.min(state.rooms, la);
					if (!fitsRooms(la, state.children, state.infants, lr)) return;
					state.adults = la;
					state.rooms = lr;
				}
				if (tgt === 'children' && state.children > 0) state.children--;
				if (tgt === 'infants' && state.infants > 0) state.infants--;
				if (tgt === 'rooms' && state.rooms > 1 && fitsRooms(state.adults, state.children, state.infants, state.rooms - 1)) state.rooms--;
				updateCounters();
				if (state.step === 3) renderReview();
			});
		}

		updateCounters();
	}

	// STEP 2: Hotel Rendering & Filtering
	function initStep2() {
		var searchInput = document.getElementById('mpk-hotel-search');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				state.filters.search = this.value.trim();
				renderHotels();
			});
		}

		var filterInputs = document.querySelectorAll('.mpk-filter-sidebar input[type="checkbox"]');
		for (var i = 0; i < filterInputs.length; i++) {
			filterInputs[i].addEventListener('change', function () {
				var name = this.getAttribute('name');
				var val = this.value;
				var isChecked = this.checked;

				// Star Rating: Multi-select support (select 1, 2, or all 3 stars simultaneously, or toggle off)
				if (name === 'mpk_stars') {
					var starVal = parseInt(val, 10);
					var sIdx = state.filters.stars.indexOf(starVal);
					if (isChecked && sIdx === -1) {
						state.filters.stars.push(starVal);
					} else if (!isChecked && sIdx > -1) {
						state.filters.stars.splice(sIdx, 1);
					}
				} else if (name === 'mpk_meals') {
					var mIdx = state.filters.meals.indexOf(val);
					if (isChecked && mIdx === -1) {
						state.filters.meals.push(val);
					} else if (!isChecked && mIdx > -1) {
						state.filters.meals.splice(mIdx, 1);
					}
				} else if (name === 'mpk_beds') {
					var bIdx = state.filters.beds.indexOf(val);
					if (isChecked && bIdx === -1) {
						state.filters.beds.push(val);
					} else if (!isChecked && bIdx > -1) {
						state.filters.beds.splice(bIdx, 1);
					}
				} else if (name === 'mpk_rooms') {
					var rIdx = state.filters.rooms.indexOf(val);
					if (isChecked && rIdx === -1) {
						state.filters.rooms.push(val);
					} else if (!isChecked && rIdx > -1) {
						state.filters.rooms.splice(rIdx, 1);
					}
				}

				renderHotels();
			});
		}

		var priceSlider = document.getElementById('mpk-price-range');
		var priceLabel = document.getElementById('mpk-price-max-label');
		if (priceSlider) {
			priceSlider.addEventListener('input', function () {
				state.filters.maxPrice = parseInt(this.value, 10);
				if (priceLabel) priceLabel.textContent = '$' + state.filters.maxPrice;
				renderHotels();
			});
		}

		// Accordion collapse toggles for filter sections
		var filterAccordionTitles = document.querySelectorAll('.mpk-filter-accordion-item .mpk-filter-title');
		for (var fa = 0; fa < filterAccordionTitles.length; fa++) {
			filterAccordionTitles[fa].addEventListener('click', function () {
				var item = this.closest('.mpk-filter-accordion-item');
				if (item) {
					item.classList.toggle('collapsed');
				}
			});
		}
	}

	function resetAllFilters() {
		state.filters.search = '';
		state.filters.stars = [];
		state.filters.meals = [];
		state.filters.beds = [];
		state.filters.rooms = [];
		state.filters.maxPrice = 1000;

		var searchInput = document.getElementById('mpk-hotel-search');
		if (searchInput) searchInput.value = '';

		var filterInputs = document.querySelectorAll('.mpk-filter-sidebar input[type="checkbox"]');
		for (var i = 0; i < filterInputs.length; i++) {
			filterInputs[i].checked = false;
		}

		var priceSlider = document.getElementById('mpk-price-range');
		var priceLabel = document.getElementById('mpk-price-max-label');
		if (priceSlider) priceSlider.value = '1000';
		if (priceLabel) priceLabel.textContent = '$1000';

		renderHotels();
	}

	function renderHotels() {
		var container = document.getElementById('mpk-hotels-container');
		if (!container) return;

		var locsToShow = state.selectedLocations.length > 0
			? state.selectedLocations
			: LOCATIONS.map(function (l) { return l.id; });

		var html = '';

		for (var l = 0; l < locsToShow.length; l++) {
			var locId = locsToShow[l];
			var loc = findLocation(locId);
			if (!loc) continue;

			// Hotel-level filtering: location, star rating, search text, and at least one matching room
			var hotelsForLoc = HOTELS.filter(function (h) {
				if (h.location !== locId) return false;

				// Star rating filter
				if (state.filters.stars.length > 0) {
					if (state.filters.stars.indexOf(h.stars) === -1) return false;
				}

				// Search text filter (hotel name or room names)
				if (state.filters.search) {
					var q = state.filters.search.toLowerCase();
					var matchesHotelName = (h.name || '').toLowerCase().indexOf(q) !== -1;
					var matchesAnyRoomName = (h.rooms || []).some(function (r) {
						return (r.name || '').toLowerCase().indexOf(q) !== -1;
					});
					if (!matchesHotelName && !matchesAnyRoomName) {
						return false;
					}
				}

				// Hotel must have AT LEAST ONE room satisfying all active room/bed/meal/price filters
				var hasMatchingRoom = (h.rooms || []).some(function (r) {
					return isRoomMatchingFilters(r, state.filters);
				});

				return hasMatchingRoom;
			});

			// Sort hotels by menu_order ASC so order 1 is always first
			hotelsForLoc.sort(function (a, b) {
				var orderA = (a.menu_order && a.menu_order > 0) ? a.menu_order : 99;
				var orderB = (b.menu_order && b.menu_order > 0) ? b.menu_order : 99;
				if (orderA === orderB) {
					return (a.name || '').localeCompare(b.name || '');
				}
				return orderA - orderB;
			});

			html += '<div class="mpk-location-section">';
			html += '<div class="mpk-location-heading">';
			html += '<div class="mpk-heading-bar"></div>';
			html += '<h3>' + escHtml(loc.name) + ' Hotels</h3>';
			html += '</div>';

			if (hotelsForLoc.length === 0) {
				html += '<div style="background: rgba(255, 255, 255, 0.6); border: 2px dashed var(--mpk-border); border-radius: var(--mpk-radius-lg); padding: 36px 20px; text-align: center;">';
				html += '<p style="font-size: 14px; color: var(--mpk-text-muted); margin: 0 0 12px;">No hotels match your filters in ' + escHtml(loc.name) + '.</p>';
				html += '<button type="button" class="mpk-btn mpk-btn-outline mpk-btn-reset-filters" style="font-size: 13px; padding: 8px 16px;">Clear Filters</button>';
				html += '</div>';
			} else {
				for (var hIdx = 0; hIdx < hotelsForLoc.length; hIdx++) {
					var hotel = hotelsForLoc[hIdx];
					html += '<article class="mpk-hotel-card">';
					html += '<div class="mpk-hotel-media">';
					html += '<img src="' + escHtml(resolveImageUrl(hotel.image)) + '" alt="' + escHtml(hotel.name) + '" loading="lazy" />';
					html += '</div>';

					html += '<div class="mpk-hotel-body">';
					html += '<div>';
					html += '<div class="mpk-hotel-header">';
					html += '<div>';
					html += '<div class="mpk-hotel-stars">';
					for (var s = 0; s < hotel.stars; s++) {
						html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="#facc15" stroke="#facc15" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
					}
					html += '</div>';
					html += '<h4 class="mpk-hotel-name">' + escHtml(hotel.name) + '</h4>';
					html += '<p class="mpk-hotel-area">';
					html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> ' + escHtml(hotel.area);
					html += '</p>';
					html += '</div>';

					html += '<div class="mpk-review-block-score">';
					html += '<span class="mpk-review-badge">' + escHtml(hotel.review) + '</span>';
					html += '<span class="mpk-review-label">' + escHtml(hotel.reviewLabel) + '</span>';
					html += '</div>';
					html += '</div>';

					// Amenities pills
					html += '<div class="mpk-amenities-pills">';
					for (var a = 0; a < (hotel.amenities || []).length; a++) {
						html += '<span class="mpk-pill">' + escHtml(hotel.amenities[a]) + '</span>';
					}
					html += '</div>';
					html += '</div>';

					// Rooms list - only render rooms matching active filters
					var roomsToDisplay = (hotel.rooms || []).filter(function (r) {
						return isRoomMatchingFilters(r, state.filters);
					});

					html += '<div class="mpk-rooms-list">';
					for (var rIdx = 0; rIdx < roomsToDisplay.length; rIdx++) {
						var room = roomsToDisplay[rIdx];
						var sel = getSelection(hotel.id, room.id);
						var isSel = !!sel;
						var hasCheckIn = isSel && !!sel.checkIn;
						var hasCheckOut = isSel && !!sel.checkOut;
						var nights = (hasCheckIn && hasCheckOut) ? diffDays(sel.checkIn, sel.checkOut) : 0;
						var checkInLabel = hasCheckIn ? formatDate(sel.checkIn, true) : 'Pick a date';
						var checkOutLabel = hasCheckOut ? formatDate(sel.checkOut, true) : 'Pick a date';

						html += '<div class="mpk-room-item ' + (isSel ? 'selected' : '') + '" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '">';
						html += '<div class="mpk-room-main" data-action="toggle-room" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '" data-location-id="' + escHtml(hotel.location) + '">';
						html += '<div class="mpk-room-left">';
						html += '<div class="mpk-room-checkbox">';
						if (isSel) {
							html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
						}
						html += '</div>';
						html += '<div>';
						html += '<p class="mpk-room-title">' + escHtml(room.name) + '</p>';
						html += '<p class="mpk-room-meal">' + escHtml(room.meal) + '</p>';
						html += '</div>';
						html += '</div>';

						html += '<div class="mpk-room-price">';
						html += '<span class="mpk-room-rate">$' + fmtMoney(roomRate(room)) + '</span>';
						html += '<span class="mpk-room-unit">per night</span>';
						html += '</div>';
						html += '</div>';

						// Interactive Date Picker Trigger Buttons with No Auto-Fill
						if (isSel && sel) {
							var minDate = getEarliestAllowedDate(hotel.id, room.id);
							var checkOutMin = sel.checkIn || minDate;

							html += '<div class="mpk-room-dates">';
							html += '<div class="mpk-dates-grid">';

							// Check-in trigger with overlay input
							html += '<div class="mpk-date-field-wrap">';
							html += '<div class="mpk-date-btn mpk-date-btn-checkin">';
							html += '<svg class="mpk-icon mpk-icon-calendar" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
							html += '<div class="mpk-date-btn-content">';
							html += '<span class="mpk-date-btn-label">Check-in</span>';
							html += '<span class="mpk-date-btn-val ' + (hasCheckIn ? 'has-date' : 'placeholder') + '">' + checkInLabel + '</span>';
							html += '</div>';
							html += '<input type="date" class="mpk-date-native-overlay mpk-checkin-input" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '" value="' + (sel.checkIn || '') + '" min="' + minDate + '" title="Choose check-in date" />';
							html += '</div>';
							html += '</div>';

							// Check-out trigger with overlay input
							html += '<div class="mpk-date-field-wrap">';
							html += '<div class="mpk-date-btn mpk-date-btn-checkout">';
							html += '<svg class="mpk-icon mpk-icon-calendar" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
							html += '<div class="mpk-date-btn-content">';
							html += '<span class="mpk-date-btn-label">Check-out</span>';
							html += '<span class="mpk-date-btn-val ' + (hasCheckOut ? 'has-date' : 'placeholder') + '">' + checkOutLabel + '</span>';
							html += '</div>';
							html += '<input type="date" class="mpk-date-native-overlay mpk-checkout-input" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '" value="' + (sel.checkOut || '') + '" min="' + checkOutMin + '" title="Choose check-out date" />';
							html += '</div>';
							html += '</div>';

							html += '</div>'; // End dates-grid

							if (minDate && !hasCheckIn) {
								html += '<p class="mpk-earliest-hint">Available from ' + formatDate(minDate) + ' onwards</p>';
							}

							if (hasCheckIn && hasCheckOut && nights > 0) {
								html += '<div class="mpk-room-stay-calc">';
								html += '<span>🌙 ' + nights + ' ' + (nights === 1 ? 'night' : 'nights') + '</span>';
								html += '<span class="mpk-tabular">$' + fmtMoney(roomRate(room) * nights * Math.max(1, state.rooms || 1)) + '</span>';
								html += '</div>';
							}
							html += '</div>'; // End room-dates
						}

						html += '</div>'; // End room-item
					}
					html += '</div>'; // End rooms-list

					html += '</div>'; // End hotel-body
					html += '</article>';
				}
			}

			html += '</div>'; // End location-section
		}

		container.innerHTML = html;
		bindHotelEvents();
		updateNavState();
	}

	function bindHotelEvents() {
		var toggleTriggers = document.querySelectorAll('[data-action="toggle-room"]');
		for (var i = 0; i < toggleTriggers.length; i++) {
			toggleTriggers[i].addEventListener('click', function (e) {
				e.preventDefault();
				var hotelId = this.getAttribute('data-hotel-id');
				var roomId = this.getAttribute('data-room-id');
				var locId = this.getAttribute('data-location-id');

				var existingIdx = -1;
				for (var j = 0; j < state.selections.length; j++) {
					if (state.selections[j].hotelId === hotelId && state.selections[j].roomId === roomId) {
						existingIdx = j;
						break;
					}
				}

				if (existingIdx > -1) {
					state.selections.splice(existingIdx, 1);
				} else {
					// Add room with empty dates by default - NO auto-filled dates
					state.selections.push({
						hotelId: hotelId,
						roomId: roomId,
						location: locId,
						checkIn: '',
						checkOut: ''
					});
				}
				renderHotels();
				if (state.step === 3) renderReview();
			});
		}

		var checkInBtns = document.querySelectorAll('.mpk-date-btn-checkin');
		for (var cib = 0; cib < checkInBtns.length; cib++) {
			checkInBtns[cib].addEventListener('click', function (e) {
				var inp = this.querySelector('.mpk-checkin-input');
				if (inp && e.target !== inp && typeof inp.showPicker === 'function') {
					try { inp.showPicker(); } catch (err) {}
				}
			});
		}

		var checkOutBtns = document.querySelectorAll('.mpk-date-btn-checkout');
		for (var cob = 0; cob < checkOutBtns.length; cob++) {
			checkOutBtns[cob].addEventListener('click', function (e) {
				var inp = this.querySelector('.mpk-checkout-input');
				if (inp && e.target !== inp && typeof inp.showPicker === 'function') {
					try { inp.showPicker(); } catch (err) {}
				}
			});
		}

		var checkInInputs = document.querySelectorAll('.mpk-checkin-input');
		for (var c = 0; c < checkInInputs.length; c++) {
			checkInInputs[c].addEventListener('click', function () {
				if (typeof this.showPicker === 'function') {
					try { this.showPicker(); } catch (err) {}
				}
			});
			checkInInputs[c].addEventListener('change', function () {
				var hotelId = this.getAttribute('data-hotel-id');
				var roomId = this.getAttribute('data-room-id');
				var sel = getSelection(hotelId, roomId);
				if (sel) {
					sel.checkIn = this.value;
					if (sel.checkOut && sel.checkOut <= sel.checkIn) {
						sel.checkOut = '';
					}
					renderHotels();
					if (state.step === 3) renderReview();
				}
			});
		}

		var checkOutInputs = document.querySelectorAll('.mpk-checkout-input');
		for (var o = 0; o < checkOutInputs.length; o++) {
			checkOutInputs[o].addEventListener('click', function () {
				if (typeof this.showPicker === 'function') {
					try { this.showPicker(); } catch (err) {}
				}
			});
			checkOutInputs[o].addEventListener('change', function () {
				var hotelId = this.getAttribute('data-hotel-id');
				var roomId = this.getAttribute('data-room-id');
				var sel = getSelection(hotelId, roomId);
				if (sel) {
					sel.checkOut = this.value;
					renderHotels();
					if (state.step === 3) renderReview();
				}
			});
		}

		var resetBtns = document.querySelectorAll('.mpk-btn-reset-filters');
		for (var r = 0; r < resetBtns.length; r++) {
			resetBtns[r].addEventListener('click', function () {
				resetAllFilters();
			});
		}
	}

	// STEP 3: Review & Details
	function initStep3() {
		var nameInput = document.getElementById('mpk-lead-name');
		var mobileInput = document.getElementById('mpk-lead-mobile');
		var emailInput = document.getElementById('mpk-lead-email');
		var countryInput = document.getElementById('mpk-lead-country');
		var passportInput = document.getElementById('mpk-lead-passport');
		var requestInput = document.getElementById('mpk-lead-request');
		var termsCheckbox = document.getElementById('mpk-terms-agree');

		if (nameInput) nameInput.value = state.form.name || '';
		if (mobileInput) mobileInput.value = state.form.mobile || '';
		if (emailInput) emailInput.value = state.form.email || '';
		if (countryInput) countryInput.value = state.form.country || '';
		if (passportInput) passportInput.value = state.form.passport || '';
		if (requestInput) requestInput.value = state.form.request || '';
		if (termsCheckbox) termsCheckbox.checked = !!state.agreed;

		if (nameInput) {
			nameInput.addEventListener('input', function () {
				state.form.name = this.value;
				updateNavState();
			});
		}
		if (mobileInput) {
			mobileInput.addEventListener('input', function () {
				state.form.mobile = this.value;
			});
		}
		if (emailInput) {
			emailInput.addEventListener('input', function () {
				state.form.email = this.value;
				updateNavState();
			});
		}
		if (countryInput) {
			countryInput.addEventListener('input', function () {
				state.form.country = this.value;
			});
		}
		if (passportInput) {
			passportInput.addEventListener('input', function () {
				state.form.passport = this.value;
			});
		}
		if (requestInput) {
			requestInput.addEventListener('input', function () {
				state.form.request = this.value;
			});
		}
		if (termsCheckbox) {
			termsCheckbox.addEventListener('change', function () {
				state.agreed = this.checked;
				updateNavState();
			});
		}

		var dropzone = document.getElementById('mpk-passport-drop');
		var fileInput = document.getElementById('mpk-passport-file');
		var dropLabel = document.getElementById('mpk-passport-drop-label');
		var dropInfo = document.getElementById('mpk-passport-drop-fileinfo');
		var dropFilename = document.getElementById('mpk-passport-filename');
		var dropRemove = document.getElementById('mpk-passport-remove');

		function setPassportFile(file) {
			if (!file) {
				state.form.fileName = '';
				state.passportFile = null;
				if (fileInput) fileInput.value = '';
				if (dropLabel) dropLabel.style.display = 'flex';
				if (dropInfo) dropInfo.style.display = 'none';
				return;
			}
			state.form.fileName = file.name;
			state.passportFile = file;
			if (dropFilename) dropFilename.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
			if (dropLabel) dropLabel.style.display = 'none';
			if (dropInfo) dropInfo.style.display = 'flex';
		}

		if (dropzone && fileInput) {
			// Prevent click event on fileInput from bubbling back to dropzone
			fileInput.addEventListener('click', function (e) {
				e.stopPropagation();
			});

			dropzone.addEventListener('click', function (e) {
				if (e.target === dropRemove || (dropRemove && dropRemove.contains(e.target))) {
					return;
				}
				fileInput.click();
			});

			fileInput.addEventListener('change', function () {
				if (this.files && this.files[0]) {
					setPassportFile(this.files[0]);
				}
			});

			// Drag & Drop handlers
			var dragEventsEnter = ['dragenter', 'dragover'];
			for (var dei = 0; dei < dragEventsEnter.length; dei++) {
				dropzone.addEventListener(dragEventsEnter[dei], function (e) {
					e.preventDefault();
					e.stopPropagation();
					dropzone.style.borderColor = 'var(--mpk-primary, #0284c7)';
					dropzone.style.backgroundColor = '#f0f9ff';
				});
			}

			var dragEventsLeave = ['dragleave', 'drop'];
			for (var del = 0; del < dragEventsLeave.length; del++) {
				dropzone.addEventListener(dragEventsLeave[del], function (e) {
					e.preventDefault();
					e.stopPropagation();
					dropzone.style.borderColor = '';
					dropzone.style.backgroundColor = '';
				});
			}

			dropzone.addEventListener('drop', function (e) {
				if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
					setPassportFile(e.dataTransfer.files[0]);
				}
			});

			if (dropRemove) {
				dropRemove.addEventListener('click', function (e) {
					e.stopPropagation();
					setPassportFile(null);
				});
			}

			// Restore file info state if already chosen
			if (state.passportFile || (state.form && state.form.fileName)) {
				var initialName = state.passportFile ? state.passportFile.name : state.form.fileName;
				var initialSize = state.passportFile ? ' (' + (state.passportFile.size / (1024 * 1024)).toFixed(2) + ' MB)' : '';
				if (dropFilename) dropFilename.textContent = initialName + initialSize;
				if (dropLabel) dropLabel.style.display = 'none';
				if (dropInfo) dropInfo.style.display = 'flex';
			}
		}

		var accHeaders = document.querySelectorAll('.mpk-accordion-header');
		for (var a = 0; a < accHeaders.length; a++) {
			accHeaders[a].addEventListener('click', function () {
				var parentItem = this.closest('.mpk-accordion-item');
				if (parentItem) {
					parentItem.classList.toggle('open');
				}
			});
		}
	}

	function renderReview() {
		var pricing = calcPricing();

		var statTravelers = document.getElementById('mpk-review-stat-travelers');
		var statNights = document.getElementById('mpk-review-stat-nights');
		if (statTravelers) {
			statTravelers.textContent = state.adults + 'A · ' + state.children + 'C · ' + state.infants + 'I';
		}
		if (statNights) {
			statNights.textContent = pricing.totalNights || '—';
		}

		var staysContainer = document.getElementById('mpk-review-stays-list');
		if (staysContainer) {
			var groupedLocs = {};
			for (var i = 0; i < state.selections.length; i++) {
				var sel = state.selections[i];
				if (!groupedLocs[sel.location]) groupedLocs[sel.location] = [];
				groupedLocs[sel.location].push(sel);
			}

			var staysHtml = '';
			var locKeys = Object.keys(groupedLocs);
			if (locKeys.length === 0) {
				staysHtml = '<p style="font-size: 13px; color: var(--mpk-text-muted); font-style: italic;">No rooms selected.</p>';
			} else {
				for (var k = 0; k < locKeys.length; k++) {
					var locId = locKeys[k];
					var loc = findLocation(locId);
					var locSelections = groupedLocs[locId];
					var locNights = 0;
					for (var n = 0; n < locSelections.length; n++) {
						if (locSelections[n].checkIn && locSelections[n].checkOut) {
							locNights += diffDays(locSelections[n].checkIn, locSelections[n].checkOut);
						}
					}

					staysHtml += '<div style="border: 1px solid var(--mpk-border); border-radius: 16px; padding: 16px 20px; margin-bottom: 14px; background: #f8fafc;">';
					staysHtml += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">';
					staysHtml += '<span style="font-weight: 600; font-size: 14px; color: var(--mpk-text); display: flex; align-items: center; gap: 6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> ' + escHtml(loc ? loc.name : locId) + '</span>';
					staysHtml += '<span style="font-size: 12px; color: var(--mpk-text-muted);">' + locNights + ' nights</span>';
					staysHtml += '</div>';

					for (var sIdx = 0; sIdx < locSelections.length; sIdx++) {
						var item = locSelections[sIdx];
						var hotel = findHotel(item.hotelId);
						var room = hotel ? findRoom(hotel, item.roomId) : null;
						var rNights = (item.checkIn && item.checkOut) ? diffDays(item.checkIn, item.checkOut) : 0;
						var roomTotal = room ? (roomRate(room) * rNights * Math.max(1, state.rooms || 1)) : 0;

						staysHtml += '<div style="background: #ffffff; border: 1px solid var(--mpk-border); border-radius: 12px; padding: 12px 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">';
						staysHtml += '<div>';
						staysHtml += '<p style="margin: 0; font-weight: 600; font-size: 14px; color: var(--mpk-text);">' + escHtml(hotel ? hotel.name : '') + '</p>';
						staysHtml += '<p style="margin: 2px 0 0; font-size: 12px; color: var(--mpk-text-muted);">';
						staysHtml += escHtml(room ? room.name : '') + ' · ' + escHtml(room ? room.meal : '') + ' · ' + rNights + ' ' + (rNights === 1 ? 'night' : 'nights');
						staysHtml += '</p>';
						if (item.checkIn && item.checkOut) {
							staysHtml += '<p style="margin: 3px 0 0; font-size: 11px; color: var(--mpk-text-muted);">';
							staysHtml += formatDate(item.checkIn) + ' → ' + formatDate(item.checkOut);
							staysHtml += '</p>';
						}
						staysHtml += '</div>';
						staysHtml += '<div style="font-weight: 700; font-size: 15px; font-variant-numeric: tabular-nums;">$' + roomTotal.toFixed(2) + '</div>';
						staysHtml += '</div>';
					}

					staysHtml += '</div>';
				}
			}
			staysContainer.innerHTML = staysHtml;
		}

		// Reference-Matched Booking Summary & Final Pricing (clean summary, no lengthy table)
		var pricingContainer = document.getElementById('mpk-review-pricing-breakdown');
		if (pricingContainer) {
			var groupedSummary = {};
			for (var si = 0; si < state.selections.length; si++) {
				var sl = state.selections[si];
				if (!groupedSummary[sl.location]) groupedSummary[sl.location] = [];
				groupedSummary[sl.location].push(sl);
			}

			var pHtml = '';
			pHtml += '<div class="mpk-summary-ocean-header">';
			pHtml += '<p class="mpk-summary-header-sub">Final Pricing</p>';
			pHtml += '<h4 class="mpk-summary-header-title">Booking Summary</h4>';
			pHtml += '</div>';

			pHtml += '<div class="mpk-summary-card-body">';
			var sLocKeys = Object.keys(groupedSummary);
			if (sLocKeys.length === 0) {
				pHtml += '<p style="font-style: italic; color: var(--mpk-text-muted); padding: 16px; margin: 0;">No rooms selected.</p>';
			} else {
				for (var sk = 0; sk < sLocKeys.length; sk++) {
					var sLocId = sLocKeys[sk];
					var sLoc = findLocation(sLocId);
					var sItems = groupedSummary[sLocId];
					var sLocTotal = 0;
					for (var sm = 0; sm < sItems.length; sm++) {
						var sh = findHotel(sItems[sm].hotelId);
						var sr = sh ? findRoom(sh, sItems[sm].roomId) : null;
						var sn = (sItems[sm].checkIn && sItems[sm].checkOut) ? diffDays(sItems[sm].checkIn, sItems[sm].checkOut) : 0;
						if (sr && sn > 0) sLocTotal += roomRate(sr) * sn * pricing.rooms;
					}

					pHtml += '<div class="mpk-summary-loc-card">';
					pHtml += '<div class="mpk-summary-loc-header">';
					pHtml += '<div class="mpk-summary-loc-title">';
					pHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
					pHtml += '<span>' + escHtml(sLoc ? sLoc.name : sLocId) + '</span>';
					pHtml += '</div>';
					pHtml += '<span class="mpk-summary-loc-price tabular-nums">$' + sLocTotal.toFixed(2) + '</span>';
					pHtml += '</div>';

					pHtml += '<div class="mpk-summary-room-items">';
					for (var sIdx2 = 0; sIdx2 < sItems.length; sIdx2++) {
						var sItem = sItems[sIdx2];
						var sHotel = findHotel(sItem.hotelId);
						var sRoom = sHotel ? findRoom(sHotel, sItem.roomId) : null;
						var sNights = (sItem.checkIn && sItem.checkOut) ? diffDays(sItem.checkIn, sItem.checkOut) : 0;
						var sPrice = roomRate(sRoom);
						var sItemTotal = sPrice * sNights * pricing.rooms;

						pHtml += '<div class="mpk-summary-room-row">';
						pHtml += '<div class="mpk-summary-room-meta">';
						pHtml += '<span class="mpk-summary-hotel-name">' + escHtml(sHotel ? sHotel.name : '') + '</span>';
						pHtml += '<span class="mpk-summary-sep">·</span>';
						pHtml += '<span>' + escHtml(sRoom ? sRoom.name : '') + '</span>';
						pHtml += '<span class="mpk-summary-sep">·</span>';
						pHtml += '<span>' + sNights + ' ' + (sNights === 1 ? 'night' : 'nights') + ' × $' + fmtMoney(sPrice) + (pricing.rooms > 1 ? ' × ' + pricing.rooms + ' rooms' : '') + '</span>';
						pHtml += '</div>';
						pHtml += '<span class="mpk-summary-room-total tabular-nums">$' + sItemTotal.toFixed(2) + '</span>';
						pHtml += '</div>';
					}
					pHtml += '</div>'; // End mpk-summary-room-items
					pHtml += '</div>'; // End mpk-summary-loc-card
				}
			}

			// Transparent price breakdown
			if (pricing.subtotal > 0) {
				var bRows = [['Rooms', pricing.roomCost]];
				if (pricing.extraAdultCost > 0) bRows.push(['Extra adults (' + pricing.extraAdults + ')', pricing.extraAdultCost]);
				if (pricing.childCost > 0) bRows.push(['Children (' + state.children + ')', pricing.childCost]);
				if (state.infants > 0) bRows.push(['Infants (' + state.infants + ')', null]);
				bRows.push(['Tax', pricing.tax]);
				if (pricing.extras > 0) bRows.push(['Extra charges', pricing.extras]);
				if (pricing.service > 0) bRows.push(['Service fee', pricing.service]);

				pHtml += '<div class="mpk-summary-room-items mpk-summary-breakdown" style="margin: 4px 0 12px;">';
				for (var bi = 0; bi < bRows.length; bi++) {
					pHtml += '<div class="mpk-summary-room-row">';
					pHtml += '<div class="mpk-summary-room-meta"><span>' + escHtml(bRows[bi][0]) + '</span></div>';
					pHtml += '<span class="mpk-summary-room-total tabular-nums">' + (bRows[bi][1] === null ? 'Free' : '$' + fmtMoney(bRows[bi][1])) + '</span>';
					pHtml += '</div>';
				}
				pHtml += '</div>';
			}

			// Clean Grand Total Banner matching Step4Review.tsx
			pHtml += '<div class="mpk-grand-total-banner">';
			pHtml += '<div>';
			pHtml += '<p class="mpk-grand-total-label">Grand Total</p>';
			pHtml += '<p class="mpk-grand-total-amount">$' + pricing.total.toFixed(2) + '</p>';
			pHtml += '<p class="mpk-grand-total-note">Price is valid for BD passport holders only.</p>';
			pHtml += '</div>';
			pHtml += '<div class="mpk-grand-total-badge">';
			pHtml += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-8 9-8 9s-8-4-8-9V5l8-3 8 3v8z"/><polyline points="9 12 11 14 15 10"/></svg>';
			pHtml += '<span>Secure · SSL encrypted</span>';
			pHtml += '</div>';
			pHtml += '</div>';

			pHtml += '</div>'; // End mpk-summary-card-body

			pricingContainer.innerHTML = pHtml;
		}

		var accPackageBody = document.getElementById('mpk-acc-package-body');
		if (accPackageBody) {
			var selectedHotelIds = [];
			for (var h = 0; h < state.selections.length; h++) {
				if (selectedHotelIds.indexOf(state.selections[h].hotelId) === -1) {
					selectedHotelIds.push(state.selections[h].hotelId);
				}
			}

			if (selectedHotelIds.length === 0) {
				accPackageBody.innerHTML = '<p style="font-style: italic; color: var(--mpk-text-muted); margin: 0;">Select a hotel to see its inclusions and exclusions.</p>';
			} else {
				var incHtml = '';
				for (var sh = 0; sh < selectedHotelIds.length; sh++) {
					var hObj = findHotel(selectedHotelIds[sh]);
					if (!hObj) continue;

					var meals = [];
					for (var sm = 0; sm < state.selections.length; sm++) {
						if (state.selections[sm].hotelId === hObj.id) {
							var rObj = findRoom(hObj, state.selections[sm].roomId);
							if (rObj && meals.indexOf(rObj.meal) === -1) {
								meals.push(rObj.meal);
							}
						}
					}

					var includesList = [
						'Return airport / speedboat transfers',
						'Daily housekeeping',
						'Welcome drink on arrival',
						'24/7 concierge support'
					];
					for (var mIdx = 0; mIdx < meals.length; mIdx++) {
						includesList.unshift(meals[mIdx] + ' meal plan');
					}
					for (var am = 0; am < (hObj.amenities || []).length; am++) {
						includesList.push(hObj.amenities[am]);
					}

					var excludesList = [
						'International flights',
						'Travel insurance',
						'Personal expenses',
						'Tips & gratuities',
						'Optional excursions'
					];

					incHtml += '<div style="margin-bottom: 20px; border: 1px solid var(--mpk-border); border-radius: 14px; padding: 16px; background: #f8fafc;">';
					incHtml += '<h5 style="font-size: 15px; font-weight: 600; margin: 0 0 12px; color: var(--mpk-text);">' + escHtml(hObj.name) + '</h5>';
					incHtml += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">';

					incHtml += '<div>';
					incHtml += '<p style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #059669; margin: 0 0 8px;">Includes</p>';
					incHtml += '<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--mpk-text-muted); line-height: 1.6;">';
					for (var inc = 0; inc < includesList.length; inc++) {
						incHtml += '<li>' + escHtml(includesList[inc]) + '</li>';
					}
					incHtml += '</ul>';
					incHtml += '</div>';

					incHtml += '<div>';
					incHtml += '<p style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #dc2626; margin: 0 0 8px;">Excludes</p>';
					incHtml += '<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--mpk-text-muted); line-height: 1.6;">';
					for (var exc = 0; exc < excludesList.length; exc++) {
						incHtml += '<li>' + escHtml(excludesList[exc]) + '</li>';
					}
					incHtml += '</ul>';
					incHtml += '</div>';

					incHtml += '</div>';
					incHtml += '</div>';
				}
				accPackageBody.innerHTML = incHtml;
			}
		}
	}

	// STEP 4: Payment Selection
	function initStep4() {
		var paymentCards = document.querySelectorAll('.mpk-payment-card');
		for (var i = 0; i < paymentCards.length; i++) {
			paymentCards[i].addEventListener('click', function () {
				if (this.classList.contains('disabled')) return;
				var method = this.getAttribute('data-payment-method');
				state.paymentMethod = method;

				for (var j = 0; j < paymentCards.length; j++) {
					paymentCards[j].classList.remove('selected');
				}
				this.classList.add('selected');
				updateNavState();
			});
		}
	}

	// STEP 5: Confirmation
	function renderConfirmation() {
		if (!state.confirmationCode) {
			state.confirmationCode = generateConfirmationCode();
		}

		var codeEl = document.getElementById('mpk-confirm-code-display');
		var emailEl = document.getElementById('mpk-confirm-email-display');
		var amountEl = document.getElementById('mpk-confirm-amount-display');
		var instructionsEl = document.getElementById('mpk-payment-instructions-body');

		if (codeEl) codeEl.textContent = state.confirmationCode;
		if (emailEl) emailEl.textContent = state.form.email || 'your email';

		var pricing = calcPricing();
		var confirmedTotal = (typeof state.serverTotal === 'number') ? state.serverTotal : pricing.total;
		if (amountEl) amountEl.textContent = '$' + confirmedTotal.toFixed(2);

		if (instructionsEl) {
			// Payment & concierge details come from admin Settings (same source as the email)
			var paySettings = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.settings) ? window.MPK_INITIAL_DATA.settings : {};
			var instHtml = '';
			if (state.paymentMethod === 'office') {
				instHtml += '<div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">';
				instHtml += '<div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(2, 132, 199, 0.1); color: var(--mpk-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg></div>';
				instHtml += '<div>';
				instHtml += '<p style="font-weight: 600; margin: 0 0 2px;">Office Visit Payment</p>';
				instHtml += '<p style="font-size: 13px; color: var(--mpk-text-muted); margin: 0;">Please visit our office to complete payment within 48 hours to secure your booking.</p>';
				instHtml += '</div>';
				instHtml += '</div>';

				instHtml += '<div style="background: #f8fafc; border: 1px solid var(--mpk-border); border-radius: 12px; padding: 14px 18px; font-size: 13px; line-height: 1.6;">';
				if (paySettings.office_address) instHtml += '<p style="margin: 0;"><strong>Address:</strong> ' + escHtml(paySettings.office_address) + '</p>';
				if (paySettings.support_phone) instHtml += '<p style="margin: 0;"><strong>Phone:</strong> ' + escHtml(paySettings.support_phone) + '</p>';
				if (paySettings.support_email) instHtml += '<p style="margin: 0;"><strong>Email:</strong> ' + escHtml(paySettings.support_email) + '</p>';
				if (paySettings.office_hours) instHtml += '<p style="margin: 0;"><strong>Hours:</strong> ' + escHtml(paySettings.office_hours) + '</p>';
				instHtml += '</div>';
			} else if (state.paymentMethod === 'bank') {
				instHtml += '<div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">';
				instHtml += '<div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(2, 132, 199, 0.1); color: var(--mpk-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="2" x2="22" y1="22" y2="22"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M4 18v-8"/><path d="M8 18v-8"/><path d="M12 18v-8"/><path d="M16 18v-8"/><path d="M20 18v-8"/><polygon points="12 2 20 6 4 6 12 2"/></svg></div>';
				instHtml += '<div>';
				instHtml += '<p style="font-weight: 600; margin: 0 0 2px;">Bank Transfer</p>';
				instHtml += '<p style="font-size: 13px; color: var(--mpk-text-muted); margin: 0;">Please transfer the total amount to the account below. Your booking is confirmed upon receipt.</p>';
				instHtml += '</div>';
				instHtml += '</div>';

				instHtml += '<div style="background: #f8fafc; border: 1px solid var(--mpk-border); border-radius: 12px; padding: 14px 18px; font-size: 13px; line-height: 1.6;">';
				if (paySettings.bank_name) instHtml += '<p style="margin: 0;"><strong>Bank:</strong> ' + escHtml(paySettings.bank_name) + '</p>';
				if (paySettings.bank_account_name) instHtml += '<p style="margin: 0;"><strong>Account Name:</strong> ' + escHtml(paySettings.bank_account_name) + '</p>';
				if (paySettings.bank_account_no) instHtml += '<p style="margin: 0;"><strong>Account Number:</strong> ' + escHtml(paySettings.bank_account_no) + '</p>';
				if (paySettings.bank_swift) instHtml += '<p style="margin: 0;"><strong>SWIFT:</strong> ' + escHtml(paySettings.bank_swift) + '</p>';
				instHtml += '<p style="margin: 8px 0 0; font-size: 12px; color: var(--mpk-text-muted); font-style: italic;">Please include your booking reference (' + escHtml(state.confirmationCode) + ') in the transfer note.</p>';
				instHtml += '</div>';
			} else {
				instHtml = '<p style="font-style: italic; color: var(--mpk-text-muted);">No payment method selected.</p>';
			}
			instructionsEl.innerHTML = instHtml;
		}

		var copyBtn = document.getElementById('mpk-btn-copy-code');
		if (copyBtn) {
			copyBtn.onclick = function () {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(state.confirmationCode).then(function () {
						copyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span style="color:#059669; font-weight:600;">Copied</span>';
						setTimeout(function () {
							copyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg> <span>Copy</span>';
						}, 2000);
					});
				}
			};
		}
	}

	// Fetch a fresh booking nonce (resolves to '' on failure -> falls back to the page nonce)
	function getFreshNonce(ajaxUrl) {
		var nd = new FormData();
		nd.append('action', 'mpk_get_nonce');
		return fetch(ajaxUrl, { method: 'POST', body: nd, credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (res) { return (res && res.success && res.data && res.data.nonce) ? res.data.nonce : ''; })
			.catch(function () { return ''; });
	}

	// AJAX Booking Submission for Step 4
	function submitBooking() {
		if (isSubmitting) return;

		var btnNext = document.querySelector('.mpk-btn-next');
		var nextLabel = document.getElementById('mpk-btn-next-label');
		var hint = document.getElementById('mpk-validation-hint');

		isSubmitting = true;
		if (btnNext) btnNext.disabled = true;
		if (nextLabel) {
			nextLabel.innerHTML = '<span class="mpk-inline-spinner"></span> Submitting...';
		}
		if (hint) {
			hint.textContent = '';
			hint.style.color = '';
		}

		// Ensure spinner style is attached to document head once
		if (!document.getElementById('mpk-spinner-style')) {
			var st = document.createElement('style');
			st.id = 'mpk-spinner-style';
			st.textContent = '@keyframes mpkSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } .mpk-inline-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #ffffff; border-radius: 50%; animation: mpkSpin 0.75s linear infinite; margin-right: 6px; vertical-align: -2px; }';
			document.head.appendChild(st);
		}

		// Prepare Form Data
		var formData = new FormData();
		var ajaxUrl = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.ajax_url) ? window.MPK_INITIAL_DATA.ajax_url : '/wp-admin/admin-ajax.php';
		var nonce = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.nonce) ? window.MPK_INITIAL_DATA.nonce : '';

		formData.append('action', 'mpk_submit_booking');
		formData.append('nonce', nonce);
		formData.append('lead_name', (state.form && state.form.name) ? state.form.name.trim() : '');
		formData.append('lead_email', (state.form && state.form.email) ? state.form.email.trim() : '');
		formData.append('lead_phone', (state.form && state.form.mobile) ? state.form.mobile.trim() : '');
		formData.append('lead_country', (state.form && state.form.country) ? state.form.country.trim() : '');
		formData.append('passport_no', (state.form && state.form.passport) ? state.form.passport.trim() : '');
		var domFileInput = document.getElementById('mpk-passport-file');
		var passportFileToUpload = state.passportFile || (domFileInput && domFileInput.files && domFileInput.files[0] ? domFileInput.files[0] : null);
		if (passportFileToUpload) {
			formData.append('passport_file', passportFileToUpload);
		}
		formData.append('special_requests', (state.form && state.form.request) ? state.form.request.trim() : '');
		formData.append('payment_method', state.paymentMethod || '');
		var hpField = document.getElementById('mpk-hp-website');
		formData.append('mpk_website', hpField ? hpField.value : '');

		// Aggregate locations
		var locNames = [];
		if (state.selectedLocations && state.selectedLocations.length > 0) {
			for (var li = 0; li < state.selectedLocations.length; li++) {
				var locObj = findLocation(state.selectedLocations[li]);
				locNames.push(locObj ? locObj.name : state.selectedLocations[li]);
			}
		}
		formData.append('selected_location', locNames.join(', '));

		// Aggregate hotels, rooms, dates
		var hotelNames = [];
		var roomNames = [];
		var minCheckIn = '';
		var maxCheckOut = '';

		if (state.selections && state.selections.length > 0) {
			for (var si = 0; si < state.selections.length; si++) {
				var sel = state.selections[si];
				var h = findHotel(sel.hotelId);
				var r = h ? findRoom(h, sel.roomId) : null;
				if (h && hotelNames.indexOf(h.name) === -1) {
					hotelNames.push(h.name);
				}
				if (r && roomNames.indexOf(r.name) === -1) {
					roomNames.push(r.name);
				}
				if (sel.checkIn) {
					if (!minCheckIn || sel.checkIn < minCheckIn) minCheckIn = sel.checkIn;
				}
				if (sel.checkOut) {
					if (!maxCheckOut || sel.checkOut > maxCheckOut) maxCheckOut = sel.checkOut;
				}
			}
		}

		formData.append('hotel_name', hotelNames.join(', '));
		formData.append('room_name', roomNames.join(', '));
		formData.append('check_in', minCheckIn);
		formData.append('check_out', maxCheckOut);

		// Counts and Pricing
		formData.append('adults', state.adults || 1);
		formData.append('children', state.children || 0);
		formData.append('infants', state.infants || 0);
		formData.append('rooms_count', state.rooms || 1);

		// Raw selections: server re-validates rooms/dates and recalculates the price
		var selPayload = [];
		for (var sp = 0; sp < state.selections.length; sp++) {
			selPayload.push({
				hotel_id: state.selections[sp].hotelId,
				room_id: state.selections[sp].roomId,
				location: state.selections[sp].location,
				check_in: state.selections[sp].checkIn || '',
				check_out: state.selections[sp].checkOut || ''
			});
		}
		formData.append('selections', JSON.stringify(selPayload));

		var pricing = calcPricing();
		formData.append('grand_total', pricing.total ? pricing.total.toFixed(2) : '0.00');

		// Send AJAX POST (refresh nonce first so cached pages never submit an expired one)
		getFreshNonce(ajaxUrl)
		.then(function (freshNonce) {
			if (freshNonce) formData.set('nonce', freshNonce);
			return fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			});
		})
		.then(function (response) {
			return response.json();
		})
		.then(function (result) {
			isSubmitting = false;
			if (result && result.success && result.data && result.data.reference_id) {
				state.confirmationCode = result.data.reference_id;
				state.serverTotal = (typeof result.data.grand_total === 'number') ? result.data.grand_total : null;
				setStep(5);
			} else {
				var errMsg = (result && result.data && result.data.message) ? result.data.message : 'Booking submission failed. Please try again.';
				if (hint) {
					hint.textContent = errMsg;
					hint.style.color = '#dc2626';
				}
				updateNavState();
			}
		})
		.catch(function (error) {
			isSubmitting = false;
			if (hint) {
				hint.textContent = 'Server connection error. Please try again.';
				hint.style.color = '#dc2626';
			}
			updateNavState();
		});
	}

	// Stepper Navigation
	function initStepperNav() {
		var stepColumns = document.querySelectorAll('.mpk-step-column');
		for (var i = 0; i < stepColumns.length; i++) {
			stepColumns[i].addEventListener('click', function () {
				var targetStep = parseInt(this.getAttribute('data-step'), 10);
				if (targetStep === 5 && !state.confirmationCode) {
					return;
				}
				if (isSubmitting) return;
				setStep(targetStep);
			});
		}

		var btnBack = document.querySelector('.mpk-btn-back');
		var btnNext = document.querySelector('.mpk-btn-next');

		if (btnBack) {
			btnBack.addEventListener('click', function () {
				if (isSubmitting) return;
				setStep(state.step - 1);
			});
		}

		if (btnNext) {
			btnNext.addEventListener('click', function (e) {
				e.preventDefault();
				if (!canContinue() || isSubmitting) return;

				if (state.step === 4) {
					submitBooking();
				} else {
					setStep(state.step + 1);
				}
			});
		}
	}

	// Bootstrap
	function initApp() {
		initStep1();
		initStep2();
		initStep3();
		initStep4();
		initStepperNav();

		renderHotels();
		renderReview();
		updateNavState();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initApp);
	} else {
		initApp();
	}

	window.MPKBookingApp = {
		getState: function () { return state; },
		setStep: setStep,
		renderHotels: renderHotels,
		renderReview: renderReview,
		calcPricing: calcPricing,
		resetFilters: resetAllFilters,
		submitBooking: submitBooking,
		version: '1.0.1'
	};
})();
