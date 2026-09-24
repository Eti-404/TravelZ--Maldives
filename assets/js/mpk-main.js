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
			minPrice: 0,
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

		// 1. Price range filter (min & max per night)
		if (typeof filters.maxPrice === 'number') {
			if (roomRate(room) > filters.maxPrice) return false;
		}
		if (typeof filters.minPrice === 'number') {
			if (roomRate(room) < filters.minPrice) return false;
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

	// Reference style for nightly rates & stay totals: "$120", "$132.50"
	function fmtPlain(n) {
		var v = Math.round((parseFloat(n) || 0) * 100) / 100;
		return (v % 1 === 0) ? String(v) : v.toFixed(2);
	}

	// Active currency from admin Settings (symbol, position, decimals)
	function getCurrency() {
		var c = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.currency) ? window.MPK_INITIAL_DATA.currency : {};
		return {
			symbol: (c.symbol !== undefined && c.symbol !== '') ? String(c.symbol) : '$',
			position: c.position || 'left',
			decimals: (c.decimals === 0 || c.decimals === '0') ? 0 : 2
		};
	}

	// Format money in the active currency.
	// exact=true  -> always currency decimals (totals, e.g. "৳12,500" / "$836.80")
	// exact=false -> no trailing zeros (nightly rates, e.g. "$120", "$132.50")
	function money(n, exact) {
		var c = getCurrency();
		var v = parseFloat(n) || 0;
		var minD = exact ? c.decimals : 0;
		var maxD = c.decimals;
		var num = v.toLocaleString('en-US', { minimumFractionDigits: minD, maximumFractionDigits: maxD });
		switch (c.position) {
			case 'left_space': return c.symbol + ' ' + num;
			case 'right': return num + c.symbol;
			case 'right_space': return num + ' ' + c.symbol;
			default: return c.symbol + num;
		}
	}

	// Amenity pill icons (reference: Pool=waves, Wifi=wifi, Restaurant=utensils, others=sparkles)
	var SVG_ATTR = ' width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
	var AMENITY_ICONS = {
		waves: '<svg' + SVG_ATTR + '><path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/></svg>',
		wifi: '<svg' + SVG_ATTR + '><path d="M12 20h.01"/><path d="M2 8.82a15 15 0 0 1 20 0"/><path d="M5 12.859a10 10 0 0 1 14 0"/><path d="M8.5 16.429a5 5 0 0 1 7 0"/></svg>',
		utensils: '<svg' + SVG_ATTR + '><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
		sparkles: '<svg' + SVG_ATTR + '><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>'
	};
	function amenityIcon(name) {
		var n = String(name || '').toLowerCase();
		if (n.indexOf('pool') > -1) return AMENITY_ICONS.waves;
		if (n.indexOf('wifi') > -1 || n.indexOf('wi-fi') > -1) return AMENITY_ICONS.wifi;
		if (n.indexOf('restaurant') > -1) return AMENITY_ICONS.utensils;
		return AMENITY_ICONS.sparkles;
	}

	var MOON_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';

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
				nextLabel.textContent = isSubmitting ? 'Submitting...' : 'Continue';
			} else {
				nextLabel.textContent = 'Continue';
			}
		}

		var ok = canContinue();
		btnNext.disabled = !ok || isSubmitting;

		// A failed submission stays visible (red) until the user changes step / payment or retries
		var hintWrap = hint ? hint.parentNode : null;
		if (hintWrap) hintWrap.classList.toggle('mpk-has-error', !!(state.submitError && state.step === 4));
		if (hint && !isSubmitting && state.submitError && state.step === 4) {
			hint.textContent = state.submitError;
			hint.style.color = '#dc2626';
			return;
		}

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
		state.submitError = '';
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
		var priceSliderMin = document.getElementById('mpk-price-range-min');
		setupPriceRange();
		if (priceSlider) {
			priceSlider.addEventListener('input', function () {
				var v = parseInt(this.value, 10);
				if (priceSliderMin && v < parseInt(priceSliderMin.value, 10)) { v = parseInt(priceSliderMin.value, 10); this.value = v; }
				state.filters.maxPrice = v;
				syncPriceRange();
				renderHotels();
			});
		}
		if (priceSliderMin) {
			priceSliderMin.addEventListener('input', function () {
				var v = parseInt(this.value, 10);
				if (priceSlider && v > parseInt(priceSlider.value, 10)) { v = parseInt(priceSlider.value, 10); this.value = v; }
				state.filters.minPrice = v;
				syncPriceRange();
				renderHotels();
			});
		}
		syncPriceRange();

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
		var pr = getPriceBounds();
		state.filters.maxPrice = pr.max;
		state.filters.minPrice = pr.min;

		var searchInput = document.getElementById('mpk-hotel-search');
		if (searchInput) searchInput.value = '';

		var filterInputs = document.querySelectorAll('.mpk-filter-sidebar input[type="checkbox"]');
		for (var i = 0; i < filterInputs.length; i++) {
			filterInputs[i].checked = false;
		}

		var priceSlider = document.getElementById('mpk-price-range');
		var priceSliderMin = document.getElementById('mpk-price-range-min');
		if (priceSlider) priceSlider.value = String(pr.max);
		if (priceSliderMin) priceSliderMin.value = String(pr.min);
		syncPriceRange();

		renderHotels();
	}

	// Price filter bounds follow the real room rates (works for any currency: $1000 or ৳150,000)
	function niceCeil(v) {
		if (v <= 0) return 100;
		var mag = Math.pow(10, Math.floor(Math.log(v) / Math.LN10));
		var steps = [1, 2, 2.5, 5, 10];
		for (var i = 0; i < steps.length; i++) {
			if (steps[i] * mag >= v) return steps[i] * mag;
		}
		return 10 * mag;
	}

	function getPriceBounds() {
		var maxRate = 0;
		for (var h = 0; h < HOTELS.length; h++) {
			var rooms = HOTELS[h].rooms || [];
			for (var r = 0; r < rooms.length; r++) {
				maxRate = Math.max(maxRate, roomRate(rooms[r]));
			}
		}
		var max = niceCeil(maxRate);
		var step = Math.max(1, Math.round(max / 100));
		return { min: 0, max: max, step: step };
	}

	function setupPriceRange() {
		var pr = getPriceBounds();
		var ids = ['mpk-price-range-min', 'mpk-price-range'];
		for (var i = 0; i < ids.length; i++) {
			var el = document.getElementById(ids[i]);
			if (!el) continue;
			el.min = String(pr.min);
			el.max = String(pr.max);
			el.step = String(pr.step);
			el.value = String(i === 0 ? pr.min : pr.max);
		}
		state.filters.minPrice = pr.min;
		state.filters.maxPrice = pr.max;
	}

	// Dual-thumb price range: fill between thumbs + labels (reference slider look)
	function syncPriceRange() {
		var maxEl = document.getElementById('mpk-price-range');
		var minEl = document.getElementById('mpk-price-range-min');
		var fill = document.getElementById('mpk-price-range-fill');
		var minLbl = document.getElementById('mpk-price-min-label');
		var maxLbl = document.getElementById('mpk-price-max-label');
		if (!maxEl) return;
		var lo = parseFloat(maxEl.min) || 0;
		var hi = parseFloat(maxEl.max) || 1000;
		var vMin = minEl ? parseFloat(minEl.value) : lo;
		var vMax = parseFloat(maxEl.value);
		if (fill) {
			fill.style.left = ((vMin - lo) / (hi - lo) * 100) + '%';
			fill.style.right = (100 - (vMax - lo) / (hi - lo) * 100) + '%';
		}
		if (minLbl) minLbl.textContent = money(vMin);
		if (maxLbl) maxLbl.textContent = money(vMax);
		// Keep the min thumb reachable when both thumbs meet at the top end
		if (minEl) minEl.style.zIndex = (vMin >= hi - (parseFloat(maxEl.step) || 1)) ? '4' : '3';
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
						html += '<span class="mpk-pill">' + amenityIcon(hotel.amenities[a]) + escHtml(hotel.amenities[a]) + '</span>';
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
						html += '<span class="mpk-room-rate">' + escHtml(money(roomRate(room))) + '</span>';
						html += '<span class="mpk-room-unit">per night</span>';
						html += '</div>';
						html += '</div>';

						// Interactive Date Picker Trigger Buttons with No Auto-Fill
						if (isSel && sel) {
							var minDate = getEarliestAllowedDate(hotel.id, room.id);
							var checkOutMin = sel.checkIn ? addDays(sel.checkIn, 1) : addDays(minDate, 1);

							html += '<div class="mpk-room-dates">';
							html += '<div class="mpk-dates-grid">';

							// Check-in trigger with overlay input
							html += '<div class="mpk-date-field-wrap">';
							html += '<div class="mpk-date-btn mpk-date-btn-checkin" role="button" tabindex="0" aria-haspopup="dialog">';
							html += '<svg class="mpk-icon mpk-icon-calendar" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
							html += '<div class="mpk-date-btn-content">';
							html += '<span class="mpk-date-btn-label">Check-in</span>';
							html += '<span class="mpk-date-btn-val ' + (hasCheckIn ? 'has-date' : 'placeholder') + '">' + checkInLabel + '</span>';
							html += '</div>';
							html += '<input type="hidden" class="mpk-checkin-input" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '" value="' + (sel.checkIn || '') + '" min="' + minDate + '" title="Choose check-in date" />';
							html += '</div>';
							html += '</div>';

							// Check-out trigger with overlay input
							html += '<div class="mpk-date-field-wrap">';
							html += '<div class="mpk-date-btn mpk-date-btn-checkout" role="button" tabindex="0" aria-haspopup="dialog">';
							html += '<svg class="mpk-icon mpk-icon-calendar" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--mpk-primary)" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
							html += '<div class="mpk-date-btn-content">';
							html += '<span class="mpk-date-btn-label">Check-out</span>';
							html += '<span class="mpk-date-btn-val ' + (hasCheckOut ? 'has-date' : 'placeholder') + '">' + checkOutLabel + '</span>';
							html += '</div>';
							html += '<input type="hidden" class="mpk-checkout-input" data-hotel-id="' + escHtml(hotel.id) + '" data-room-id="' + escHtml(room.id) + '" value="' + (sel.checkOut || '') + '" min="' + checkOutMin + '" title="Choose check-out date" />';
							html += '</div>';
							html += '</div>';

							html += '</div>'; // End dates-grid

							html += '<div class="mpk-room-stay-calc">';
							html += '<span class="mpk-stay-nights">' + MOON_SVG + nights + ' ' + (nights === 1 ? 'night' : 'nights') + '</span>';
							html += '<span class="mpk-tabular">' + escHtml(money(roomRate(room) * nights * Math.max(1, state.rooms || 1))) + '</span>';
							html += '</div>';
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

	// ------------------------------------------------------------------
	// Calendar popover (reference: shadcn/react-day-picker look & feel)
	// ------------------------------------------------------------------
	var MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
	var calPop = null;
	var calState = null;

	function ymd(d) {
		return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
	}

	function parseYmd(str) {
		if (!str) return null;
		var p = str.split('-');
		if (p.length < 3) return null;
		return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
	}

	function closeCalendar() {
		if (calPop && calPop.parentNode) calPop.parentNode.removeChild(calPop);
		calPop = null;
		calState = null;
		document.removeEventListener('mousedown', onCalOutside, true);
		document.removeEventListener('keydown', onCalKey, true);
		window.removeEventListener('resize', closeCalendar);
		window.removeEventListener('scroll', positionCalendar, true);
	}

	function onCalOutside(e) {
		if (!calPop) return;
		if (calPop.contains(e.target)) return;
		if (calState && calState.anchor && calState.anchor.contains(e.target)) return;
		closeCalendar();
	}

	function onCalKey(e) {
		if (e.key === 'Escape') closeCalendar();
	}

	function positionCalendar() {
		if (!calPop || !calState) return;
		var r = calState.anchor.getBoundingClientRect();
		var popH = calPop.offsetHeight;
		var popW = calPop.offsetWidth;
		var top = r.bottom + 6;
		if (top + popH > window.innerHeight - 8 && r.top - popH - 6 > 8) {
			top = r.top - popH - 6;
		}
		var left = Math.min(Math.max(8, r.left), window.innerWidth - popW - 8);
		calPop.style.top = Math.round(top) + 'px';
		calPop.style.left = Math.round(left) + 'px';
	}

	function renderCalendar() {
		if (!calPop || !calState) return;
		var view = calState.view;
		var year = view.getFullYear();
		var month = view.getMonth();
		var first = new Date(year, month, 1);
		var last = new Date(year, month + 1, 0);
		var start = new Date(year, month, 1 - first.getDay());
		var end = new Date(year, month, last.getDate() + (6 - last.getDay()));
		var todayStr = ymd(new Date());
		var minStr = calState.min || '';
		var valStr = calState.input.value || '';

		var h = '';
		h += '<div class="mpk-cal-head">';
		h += '<button type="button" class="mpk-cal-nav mpk-cal-prev" aria-label="Previous month"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>';
		h += '<span class="mpk-cal-caption">' + MONTHS_LONG[month] + ' ' + year + '</span>';
		h += '<button type="button" class="mpk-cal-nav mpk-cal-next" aria-label="Next month"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>';
		h += '</div>';
		h += '<div class="mpk-cal-grid" role="grid">';
		var wd = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
		for (var w = 0; w < 7; w++) h += '<span class="mpk-cal-wd">' + wd[w] + '</span>';
		for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
			var ds = ymd(d);
			var cls = 'mpk-cal-day';
			if (d.getMonth() !== month) cls += ' is-outside';
			if (ds === todayStr) cls += ' is-today';
			if (ds === valStr) cls += ' is-selected';
			var disabled = minStr && ds < minStr;
			h += '<button type="button" class="' + cls + '" data-date="' + ds + '"' + (disabled ? ' disabled' : '') + '>' + d.getDate() + '</button>';
		}
		h += '</div>';
		calPop.innerHTML = h;

		calPop.querySelector('.mpk-cal-prev').addEventListener('click', function () {
			calState.view = new Date(year, month - 1, 1);
			renderCalendar();
			positionCalendar();
		});
		calPop.querySelector('.mpk-cal-next').addEventListener('click', function () {
			calState.view = new Date(year, month + 1, 1);
			renderCalendar();
			positionCalendar();
		});
		var dayBtns = calPop.querySelectorAll('.mpk-cal-day');
		for (var b = 0; b < dayBtns.length; b++) {
			dayBtns[b].addEventListener('click', function () {
				if (this.disabled) return;
				var input = calState.input;
				input.value = this.getAttribute('data-date');
				closeCalendar();
				input.dispatchEvent(new Event('change', { bubbles: true }));
			});
		}
	}

	function openCalendar(anchor, input) {
		if (calState && calState.input === input) {
			closeCalendar();
			return;
		}
		closeCalendar();
		var app = document.getElementById('mpk-booking-app') || document.body;
		calPop = document.createElement('div');
		calPop.className = 'mpk-cal-popover';
		calPop.setAttribute('role', 'dialog');
		var min = input.getAttribute('min') || '';
		var base = parseYmd(input.value) || parseYmd(min) || new Date();
		calState = { anchor: anchor, input: input, min: min, view: new Date(base.getFullYear(), base.getMonth(), 1) };
		app.appendChild(calPop);
		renderCalendar();
		positionCalendar();
		document.addEventListener('mousedown', onCalOutside, true);
		document.addEventListener('keydown', onCalKey, true);
		window.addEventListener('resize', closeCalendar);
		window.addEventListener('scroll', positionCalendar, true);
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

		var dateBtns = document.querySelectorAll('.mpk-date-btn');
		for (var db = 0; db < dateBtns.length; db++) {
			dateBtns[db].addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var inp = this.querySelector('input.mpk-checkin-input, input.mpk-checkout-input');
				if (inp) openCalendar(this, inp);
			});
			dateBtns[db].addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					this.click();
				}
			});
		}

		var checkInInputs = document.querySelectorAll('.mpk-checkin-input');
		for (var c = 0; c < checkInInputs.length; c++) {
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
				staysHtml = '<p class="mpk-empty-note">No rooms selected.</p>';
			} else {
				staysHtml += '<div class="mpk-stays-list">';
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

					staysHtml += '<div class="mpk-stay-group">';
					staysHtml += '<div class="mpk-stay-group-head">';
					staysHtml += '<span class="mpk-stay-group-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>';
					staysHtml += '<p class="mpk-stay-group-name">' + escHtml(loc ? loc.name : locId) + '</p>';
					staysHtml += '<span class="mpk-stay-group-nights">' + locNights + ' nights</span>';
					staysHtml += '</div>';
					staysHtml += '<div class="mpk-stay-items">';

					for (var sIdx = 0; sIdx < locSelections.length; sIdx++) {
						var item = locSelections[sIdx];
						var hotel = findHotel(item.hotelId);
						var room = hotel ? findRoom(hotel, item.roomId) : null;
						var rNights = (item.checkIn && item.checkOut) ? diffDays(item.checkIn, item.checkOut) : 0;
						var roomTotal = room ? (roomRate(room) * rNights * Math.max(1, state.rooms || 1)) : 0;

						staysHtml += '<div class="mpk-stay-item">';
						staysHtml += '<div>';
						staysHtml += '<p class="mpk-stay-hotel">' + escHtml(hotel ? hotel.name : '') + '</p>';
						staysHtml += '<p class="mpk-stay-meta">' + escHtml(room ? room.name : '') + ' · ' + escHtml(room ? room.meal : '') + ' · ' + rNights + ' ' + (rNights === 1 ? 'night' : 'nights') + '</p>';
						if (item.checkIn && item.checkOut) {
							staysHtml += '<p class="mpk-stay-dates">' + formatDate(item.checkIn) + ' → ' + formatDate(item.checkOut) + '</p>';
						}
						staysHtml += '</div>';
						staysHtml += '<p class="mpk-stay-price mpk-tabular">' + escHtml(money(roomTotal)) + '</p>';
						staysHtml += '</div>';
					}

					staysHtml += '</div>';
					staysHtml += '</div>';
				}
				staysHtml += '</div>';
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
					pHtml += '<span class="mpk-summary-loc-price tabular-nums">' + escHtml(money(sLocTotal, true)) + '</span>';
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
						pHtml += '<span>' + sNights + ' ' + (sNights === 1 ? 'night' : 'nights') + ' × ' + escHtml(money(sPrice)) + (pricing.rooms > 1 ? ' × ' + pricing.rooms + ' rooms' : '') + '</span>';
						pHtml += '</div>';
						pHtml += '<span class="mpk-summary-room-total tabular-nums">' + escHtml(money(sItemTotal, true)) + '</span>';
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
					pHtml += '<span class="mpk-summary-room-total tabular-nums">' + (bRows[bi][1] === null ? 'Free' : escHtml(money(bRows[bi][1], true))) + '</span>';
					pHtml += '</div>';
				}
				pHtml += '</div>';
			}

			// Clean Grand Total Banner matching Step4Review.tsx
			pHtml += '<div class="mpk-grand-total-banner">';
			pHtml += '<div>';
			pHtml += '<p class="mpk-grand-total-label">Grand Total</p>';
			pHtml += '<p class="mpk-grand-total-amount">' + escHtml(money(pricing.total, true)) + '</p>';
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
				accPackageBody.innerHTML = '<p class="mpk-empty-note">Select a hotel to see its inclusions and exclusions.</p>';
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

					// Includes/excludes come from admin Settings (fallback: defaults).
					// Reference order: meal plan(s), first base include, hotel amenities, remaining base includes.
					var pkgSet = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.settings) ? window.MPK_INITIAL_DATA.settings : {};
					var baseInc = (pkgSet.hotel_includes_base && pkgSet.hotel_includes_base.length) ? pkgSet.hotel_includes_base.slice() : ['Return airport / speedboat transfers', 'Daily housekeeping', 'Welcome drink on arrival', '24/7 concierge support'];
					var includesList = [];
					for (var mIdx = 0; mIdx < meals.length; mIdx++) {
						includesList.push(meals[mIdx] + ' meal plan');
					}
					if (baseInc.length) includesList.push(baseInc[0]);
					for (var am = 0; am < (hObj.amenities || []).length; am++) {
						includesList.push(hObj.amenities[am]);
					}
					for (var bi2 = 1; bi2 < baseInc.length; bi2++) {
						includesList.push(baseInc[bi2]);
					}

					var excludesList = (pkgSet.base_excludes && pkgSet.base_excludes.length) ? pkgSet.base_excludes.slice() : ['International flights', 'Travel insurance', 'Personal expenses', 'Tips & gratuities', 'Optional excursions'];

					incHtml += '<div class="mpk-pkg-hotel">';
					incHtml += '<p class="mpk-pkg-hotel-name">' + escHtml(hObj.name) + '</p>';
					incHtml += '<div class="mpk-pkg-grid">';

					incHtml += '<div>';
					incHtml += '<p class="mpk-pkg-label mpk-pkg-label-inc">Includes</p>';
					incHtml += '<ul class="mpk-pkg-list mpk-pkg-list-inc">';
					for (var inc = 0; inc < includesList.length; inc++) {
						incHtml += '<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>' + escHtml(includesList[inc]) + '</span></li>';
					}
					incHtml += '</ul>';
					incHtml += '</div>';

					incHtml += '<div>';
					incHtml += '<p class="mpk-pkg-label mpk-pkg-label-exc">Excludes</p>';
					incHtml += '<ul class="mpk-pkg-list mpk-pkg-list-exc">';
					for (var exc = 0; exc < excludesList.length; exc++) {
						incHtml += '<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg><span>' + escHtml(excludesList[exc]) + '</span></li>';
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
				state.submitError = '';

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
		if (emailEl) {
			emailEl.textContent = state.form.email || 'your email';
			if (state.form.email) emailEl.setAttribute('href', 'mailto:' + state.form.email);
		}

		var pricing = calcPricing();
		var confirmedTotal = (typeof state.serverTotal === 'number') ? state.serverTotal : pricing.total;
		if (amountEl) amountEl.textContent = money(confirmedTotal, true);

		if (instructionsEl) {
			// Payment & concierge details come from admin Settings (same source as the email)
			var paySettings = (window.MPK_INITIAL_DATA && window.MPK_INITIAL_DATA.settings) ? window.MPK_INITIAL_DATA.settings : {};
			var instHtml = '';
			var instRow = function (k, v) { return v ? '<p><span class="mpk-inst-k">' + k + '</span> ' + escHtml(v) + '</p>' : ''; };
			if (state.paymentMethod === 'office') {
				instHtml += '<div class="mpk-inst-row"><span class="mpk-inst-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg></span><div>';
				instHtml += '<p class="mpk-inst-title">Office Visit Payment</p>';
				instHtml += '<p class="mpk-inst-desc">Please visit our office to complete payment within 48 hours to secure your booking.</p>';
				instHtml += '</div></div>';
				instHtml += '<div class="mpk-inst-details">';
				instHtml += instRow('Address:', paySettings.office_address);
				instHtml += instRow('Phone:', paySettings.support_phone);
				instHtml += instRow('Email:', paySettings.support_email);
				instHtml += instRow('Hours:', paySettings.office_hours);
				instHtml += '</div>';
			} else if (state.paymentMethod === 'bank') {
				instHtml += '<div class="mpk-inst-row"><span class="mpk-inst-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg></span><div>';
				instHtml += '<p class="mpk-inst-title">Bank Transfer</p>';
				instHtml += '<p class="mpk-inst-desc">Please transfer the total amount to the account below. Your booking is confirmed upon receipt.</p>';
				instHtml += '</div></div>';
				instHtml += '<div class="mpk-inst-details">';
				instHtml += instRow('Bank:', paySettings.bank_name);
				instHtml += instRow('Account Name:', paySettings.bank_account_name);
				instHtml += instRow('Account Number:', paySettings.bank_account_no);
				instHtml += instRow('SWIFT:', paySettings.bank_swift);
				instHtml += '<p class="mpk-inst-note">Please include your booking reference (' + escHtml(state.confirmationCode) + ') in the transfer note.</p>';
				instHtml += '</div>';
			} else {
				instHtml = '<p class="mpk-empty-note">No payment method selected.</p>';
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
		state.submitError = '';

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
				state.submitError = (result && result.data && result.data.message) ? result.data.message : 'Booking submission failed. Please try again.';
				updateNavState();
			}
		})
		.catch(function (error) {
			isSubmitting = false;
			state.submitError = 'Server connection error. Please try again.';
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
