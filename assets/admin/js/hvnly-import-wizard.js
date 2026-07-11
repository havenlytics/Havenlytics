/**
 * Property Import Wizard JavaScript
 * Handles multi-step import process with animations
 */

/**
 * WordPress 6.8+ speculative loading / View Transitions can reject with
 * AbortError ("Transition was skipped") during admin navigation. Harmless.
 */
(function suppressExpectedViewTransitionAbort() {
    if (window.__hvnlyImportWizardAbortFilter) {
        return;
    }
    window.__hvnlyImportWizardAbortFilter = true;
    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason;
        if (!reason || reason.name !== 'AbortError') {
            return;
        }
        const message = String(reason.message || '');
        if (
            message.includes('Transition was skipped')
            || message.includes('Transition was aborted')
            || message.includes('view transition')
        ) {
            event.preventDefault();
        }
    });
})();

// NOTE: release builds must not ship console debug probes.

// Current step tracking
let currentStep = 1;
const totalSteps = 4;
const WIZARD_STEP_SLUGS = {
    1: 'department',
    2: 'location',
    3: 'media',
    4: 'review',
};
const WIZARD_SLUG_TO_STEP = {
    department: 1,
    location: 2,
    media: 3,
    preferences: 4,
    review: 4,
};
let wizardUrlRoutingBound = false;
let propertyImportQuantity = window.hvnlyImportWizard?.defaultImportQuantity || 10;
let isImporting = false;
let selectedDepartment = 'sale';

// Map settings (persisted via hvnly_plugin_settings[map])
let selectedMapProvider = (window.hvnlyImportWizard && window.hvnlyImportWizard.mapProvider) || 'leaflet';
let isStep2Active = false;
let importElapsedTimer = null;
let importStartedAt = null;
let youtubePreviewBound = false;
let wizardEventsBound = false;
let quantitySliderBound = false;
let wizardAccordionsBound = false;

const WIZARD_ACCORDION_STORAGE_KEY = 'hvnly_wizard_accordion_state';
const WIZARD_DEPARTMENT_LABELS = {
    sale: 'Sale',
    rent: 'Rent',
    commercial: 'Commercial',
    let: 'Let',
};

// Demo gallery images
const demoGalleryImages = [
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/1.jpg',
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/2.jpg',
    'https://demo.havenlytics.com/wp-content/uploads/2026/03/3.jpg'
];

// All departments that will be imported
const allDepartments = ['sale', 'rent', 'commercial', 'let'];

// Import cancellation and pause variables
let importCancelled = false;
let importPaused = false;
let currentImportData = null;
let currentBatch = 0;
let currentImported = 0;
let currentCsrfToken = '';
let currentImportSessionId = '';
let currentImportXhr = null;
let pausePollTimer = null;
let importRequestInFlight = false;

/** Forward-only timeline: once a stage is reached, never move backward. */
const IMPORT_STAGE_ORDER = ['prepare', 'properties', 'images', 'sections', 'complete'];
let highestImportStageIndex = 0;

// Location step modification tracking (intent-aware).
//
// hvnlyLocationStepModified: true once the user touches the Location step in any
//   way (typing an address, editing coordinates, moving the marker, clicking the
//   map, or picking an autocomplete suggestion).
//
// hvnlyCoordinatesUserEntered: true ONLY when the latitude/longitude originate
//   from a deliberate user action — manual coordinate typing, marker drag, or
//   map click. Coordinates that were auto-populated by geocoding a typed address
//   do NOT set this flag, so an address-only edit stays an address-only override
//   and never destroys each property's own demo coordinates.
//
// The map manager reports its source ('manual' | 'geocode') through this hook;
// direct field typing is wired up in the DOMContentLoaded handler.
let hvnlyLocationStepModified = false;
let hvnlyCoordinatesUserEntered = false;
window.hvnlyImportWizardMarkLocationModified = function (source) {
    hvnlyLocationStepModified = true;
    if (source === 'manual') {
        hvnlyCoordinatesUserEntered = true;
    }
};

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// =========================================
// MAP FUNCTIONS (HvnlyImportMapManager)
// =========================================

function getMapManager() {
    return window.HvnlyImportMapManager;
}

function usesLeafletProvider(provider) {
    return provider === 'leaflet' || provider === 'openstreetmap';
}

function isGoogleMapProvider(provider) {
    return provider === 'google';
}

function setPanelHidden(panel, hidden) {
    if (!panel) {
        return;
    }
    if (hidden) {
        panel.setAttribute('hidden', '');
        panel.classList.add('hvnly-import-provider-hidden');
    } else {
        panel.removeAttribute('hidden');
        panel.classList.remove('hvnly-import-provider-hidden');
    }
}

function cleanupGoogleProviderUi() {
    getMapManager()?.teardownGooglePlacesUi?.();
    getMapManager()?.hidePlacesNotice?.();

    document.querySelectorAll('#step-panel-2 .hvnly-google-place-autocomplete').forEach((element) => {
        element.remove();
    });

    const addressInput = document.getElementById('map-address');
    if (addressInput) {
        addressInput.removeAttribute('hidden');
        addressInput.removeAttribute('aria-hidden');
    }
}

function hideAllProviderPanels() {
    document.querySelectorAll('.hvnly-import-provider-panel').forEach((panel) => {
        setPanelHidden(panel, true);
    });

    const googleApiKeyInput = document.getElementById('google-api-key');
    const saveGoogleApiKeyBtn = document.getElementById('save-google-api-key');

    if (googleApiKeyInput) {
        googleApiKeyInput.disabled = true;
        googleApiKeyInput.setAttribute('aria-hidden', 'true');
        googleApiKeyInput.setAttribute('tabindex', '-1');
    }
    if (saveGoogleApiKeyBtn) {
        setPanelHidden(saveGoogleApiKeyBtn, true);
    }
}

function getLeafletProviderNoteText(provider) {
    if (provider === 'openstreetmap') {
        return 'OpenStreetMap (OSM) tiles active. No Google API key required.';
    }
    if (provider === 'leaflet') {
        return 'Leaflet map with OpenStreetMap tiles. No Google API key required.';
    }
    return '';
}

function updateMapProviderConditionalUi(provider) {
    const activeProvider = provider || selectedMapProvider || 'leaflet';

    cleanupGoogleProviderUi();
    hideAllProviderPanels();

    const googlePanel = document.querySelector('.hvnly-import-provider-google');
    const leafletPanel = document.querySelector('.hvnly-import-provider-leaflet');
    const googleApiKeyInput = document.getElementById('google-api-key');
    const saveGoogleApiKeyBtn = document.getElementById('save-google-api-key');
    const leafletNote = document.querySelector('.hvnly-import-leaflet-osm-note');
    const step2Panel = document.getElementById('step-panel-2');

    if (step2Panel) {
        step2Panel.setAttribute('data-active-map-provider', activeProvider);
    }

    if (isGoogleMapProvider(activeProvider)) {
        setPanelHidden(googlePanel, false);
        if (googleApiKeyInput) {
            googleApiKeyInput.disabled = false;
            googleApiKeyInput.setAttribute('aria-hidden', 'false');
            googleApiKeyInput.removeAttribute('tabindex');
        }
        if (saveGoogleApiKeyBtn) {
            setPanelHidden(saveGoogleApiKeyBtn, false);
        }
        return;
    }

    if (usesLeafletProvider(activeProvider)) {
        setPanelHidden(leafletPanel, false);
        if (leafletNote) {
            leafletNote.textContent = getLeafletProviderNoteText(activeProvider);
        }
    }
}

function syncMapProviderUi(provider) {
    selectedMapProvider = provider || 'leaflet';
    document.querySelectorAll('.hvnly--property--import-map-provider').forEach((el) => {
        const isActive = el.getAttribute('data-provider') === selectedMapProvider;
        el.classList.toggle('active', isActive);
    });
    updateMapProviderConditionalUi(selectedMapProvider);
    getMapManager()?.cleanupProviderExclusiveUi?.(selectedMapProvider);
}

function saveMapProvider(provider) {
    return jQuery.ajax({
        url: hvnlyImportWizard.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'hvnly_save_map_provider',
            nonce: hvnlyImportWizard.nonce,
            csrf_token: document.getElementById('hvnly_csrf_token')?.value || '',
            map_provider: provider,
        },
    });
}

function saveGoogleApiKey(apiKey) {
    return jQuery.ajax({
        url: hvnlyImportWizard.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'hvnly_save_google_api_key',
            nonce: hvnlyImportWizard.nonce,
            csrf_token: document.getElementById('hvnly_csrf_token')?.value || '',
            google_api_key: apiKey,
            map_provider: selectedMapProvider,
        },
    });
}

function destroyMap() {
    getMapManager()?.destroy();
}

function initMap() {
    if (!isStep2Active) return;
    const manager = getMapManager();
    if (!manager) return;
    manager.scheduleInit(selectedMapProvider, () => {
        const panel = document.getElementById('step-panel-2');
        return isStep2Active && panel && !panel.hasAttribute('hidden');
    });
}

function updateCoordinates(lat, lng) {
    const manager = getMapManager();
    if (manager?.applyFromCoordinates) {
        manager.applyFromCoordinates(lat, lng, { force: true });
        return;
    }
    const latInput = document.getElementById('map-latitude');
    const lngInput = document.getElementById('map-longitude');
    if (latInput) latInput.value = lat.toFixed(6);
    if (lngInput) lngInput.value = lng.toFixed(6);
}

function setupLeafletAutocomplete() {
    getMapManager()?.setupLeafletAddressAutocomplete?.();
}

function setupAddressAutocomplete() {
    getMapManager()?.setupAddressAutocomplete?.();
}

// =========================================
// YOUTUBE FUNCTIONS
// =========================================

function initYouTubePreview() {
    const urlInput = document.getElementById('youtube-url');
    const previewContainer = document.getElementById('youtube-preview');
    if (!urlInput || !previewContainer) return;

    const updatePreview = () => {
        const url = urlInput.value.trim();
        const videoId = extractYouTubeId(url);
        if (videoId) {
            previewContainer.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen style="width:100%;height:180px;border-radius:8px;"></iframe>`;
        } else {
            previewContainer.innerHTML = '';
        }
    };

    if (!youtubePreviewBound) {
        youtubePreviewBound = true;
        urlInput.addEventListener('input', updatePreview);
    }
    updatePreview();
}

function extractYouTubeId(url) {
    if (!url) return null;
    const patterns = [/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/, /youtube\.com\/embed\/([^/?]+)/];
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match && match[1]) return match[1];
    }
    return null;
}

// =========================================
// DEPARTMENT CONFIGURATIONS
// =========================================

function getDepartmentConfig(department) {
    const configs = {
        sale: {
            department: 'sale',
            property_types: ['cottage', 'duplex', 'flat', 'land', 'garage', 'mews', 'triplex'],
            property_status: ['for-sale', 'pending', 'sold', 'under-offer', 'exchanged'],
            badges: ['featured', 'new', 'popular'],
            tags: ['apartment', 'bungalow', 'cottage', 'duplex', 'flat', 'house', 'land', 'studio', 'townhouse'],
            locations: ['london', 'new-york', 'los-angeles', 'chicago', 'miami', 'dubai', 'paris', 'tokyo'],
            features: ['pool', 'parking', 'garden', 'security', 'elevator', 'balcony', 'fireplace', 'central-air']
        },
        rent: {
            department: 'rent',
            property_types: ['flat', 'duplex', 'triplex'],
            property_status: ['for-rent'],
            badges: ['new', 'popular'],
            tags: ['apartment', 'flat', 'studio', 'house'],
            locations: ['london', 'new-york', 'los-angeles', 'chicago', 'miami', 'dubai', 'paris'],
            features: ['parking', 'elevator', 'balcony', 'garden', 'central-air']
        },
        commercial: {
            department: 'commercial',
            property_types: ['land', 'garage'],
            property_status: ['for-sale', 'for-rent', 'pending', 'sold'],
            badges: ['featured'],
            tags: ['commercial', 'office', 'retail', 'warehouse', 'land'],
            locations: ['london', 'new-york', 'chicago', 'dubai', 'tokyo', 'singapore'],
            features: ['parking', 'elevator', 'security', 'loading-dock']
        },
        let: {
            department: 'let',
            property_types: ['flat', 'garage', 'mews'],
            property_status: ['for-rent'],
            badges: ['new'],
            tags: ['flat', 'apartment', 'house', 'studio'],
            locations: ['london', 'paris', 'new-york', 'singapore'],
            features: ['parking', 'balcony', 'garden', 'security']
        }
    };
    return configs[department] || configs.sale;
}

// =========================================
// IMPORT FUNCTIONS
// =========================================

window.importBatch = function(batch, imported, importData, csrfToken, retryAttempt, sessionId, newSession) {
    if (window.__hvnlyImportForensics) {
        window.__hvnlyImportForensics.lastAction = 'import_batch';
        window.__hvnlyImportForensics.importBatch = batch;
    }
    currentBatch = batch;
    currentImported = imported;
    currentImportData = importData;
    currentCsrfToken = csrfToken;
    if (sessionId) {
        currentImportSessionId = sessionId;
    }
    const attempt = Number.isInteger(retryAttempt) ? retryAttempt : 0;
    const maxRetries = window.hvnlyImportWizard?.importMaxRetries || 3;
    const ajaxTimeout = window.hvnlyImportWizard?.importAjaxTimeout || 180000;
    const isNewSession = !!newSession;

    if (importCancelled) { handleImportCancellation(); return; }
    if (importPaused) {
        if (pausePollTimer) {
            clearTimeout(pausePollTimer);
        }
        pausePollTimer = setTimeout(() => {
            pausePollTimer = null;
            if (!importPaused && !importCancelled && !importRequestInFlight) {
                window.importBatch(batch, imported, importData, csrfToken, attempt, currentImportSessionId, false);
            }
        }, 1000);
        return;
    }

    // Single-flight: never start a second batch while one is in progress.
    if (importRequestInFlight) {
        return;
    }

    importRequestInFlight = true;
    currentImportXhr = jQuery.ajax({
        url: hvnlyImportWizard.ajaxurl,
        type: 'POST',
        timeout: ajaxTimeout,
        data: {
            action: 'hvnly_import_properties',
            nonce: hvnlyImportWizard.nonce,
            csrf_token: csrfToken,
            batch: batch,
            imported: imported,
            total_to_import: importData.total_to_import,
            email_notifications: importData.email_notifications,
            session_id: currentImportSessionId,
            new_session: isNewSession ? 1 : 0,
            options: importData
        },
        success: function(response) {
            importRequestInFlight = false;
            currentImportXhr = null;
            if (importCancelled) { handleImportCancellation(); return; }
            if (response.success) {
                const data = response.data;
                if (data.session_id) {
                    currentImportSessionId = data.session_id;
                }
                if (typeof data.imported !== 'undefined') {
                    const importedCount = parseInt(data.imported, 10);
                    if (!Number.isNaN(importedCount)) {
                        currentImported = importedCount;
                    }
                }
                if (typeof data.next_batch !== 'undefined') {
                    const nextBatch = parseInt(data.next_batch, 10);
                    if (!Number.isNaN(nextBatch)) {
                        currentBatch = nextBatch;
                    }
                }
                if (data.cancelled) {
                    importCancelled = true;
                    handleImportCancellation();
                    return;
                }
                updateProgress(data);
                if (data.complete) { setTimeout(() => showCelebration(data.imported), 500); }
                else if (!importCancelled && !importPaused) {
                    const resumeBatch = Number.isNaN(parseInt(data.next_batch, 10))
                        ? currentBatch
                        : parseInt(data.next_batch, 10);
                    const resumeImported = Number.isNaN(parseInt(data.imported, 10))
                        ? currentImported
                        : parseInt(data.imported, 10);
                    window.importBatch(resumeBatch, resumeImported, importData, csrfToken, 0, currentImportSessionId, false);
                }
            } else { handleImportError(response.data || 'Import error', null, importData, csrfToken); }
        },
        error: function(xhr, status, error) {
            importRequestInFlight = false;
            currentImportXhr = null;

            // Aborted by Cancel — do not treat as a failure.
            if (importCancelled || status === 'abort') {
                handleImportCancellation();
                return;
            }

            const httpStatus = xhr && xhr.status ? parseInt(xhr.status, 10) : 0;
            const isRetryable = status === 'timeout'
                || httpStatus === 504
                || httpStatus === 502
                || httpStatus === 503
                || httpStatus === 0;

            if (isRetryable && attempt < maxRetries) {
                const importMessage = document.getElementById('importMessage');
                if (importMessage) {
                    importMessage.textContent = `Connection interrupted. Retrying batch ${batch + 1} (${attempt + 1}/${maxRetries})…`;
                }
                setTimeout(function() {
                    if (!importCancelled && !importPaused && !importRequestInFlight) {
                        window.importBatch(batch, imported, importData, csrfToken, attempt + 1, currentImportSessionId, false);
                    }
                }, 3000);
                return;
            }

            const detail = httpStatus ? `HTTP ${httpStatus} (${status || error || 'error'})` : `Status: ${status}`;
            handleImportError(error || 'Connection error', detail, importData, csrfToken);
        }
    });
};

function canStartFreshImport() {
    const resume = window.hvnlyImportWizard?.importResume;
    if (currentImportSessionId && currentImported > 0) {
        return false;
    }
    if (resume?.can_resume && resume.session_id) {
        return false;
    }
    return true;
}

function startOrResumeImportFromWizard() {
    if (isImporting || importRequestInFlight) {
        return;
    }

    if (!canStartFreshImport()) {
        resumeInterruptedImport();
        return;
    }

    startImportProcess();
}

async function startImportProcess() {
    if (window.__hvnlyImportForensics) {
        window.__hvnlyImportForensics.lastAction = 'import_start';
        window.__hvnlyImportForensics.importBatch = 0;
    }

    if (isImporting || importRequestInFlight) {
        return;
    }

    if (!canStartFreshImport()) {
        resumeInterruptedImport();
        return;
    }

    const maxLimit = window.hvnlyImportWizard?.maxImportLimit || 50;
    if (propertyImportQuantity > maxLimit) { propertyImportQuantity = maxLimit; }
    
    isImporting = true;
    importCancelled = false;
    importPaused = false;
    
    const quantitySlider = document.getElementById('propertyQuantitySlider');
    if (quantitySlider) propertyImportQuantity = Math.min(parseInt(quantitySlider.value), 200);
    const csrfToken = document.getElementById('hvnly_csrf_token')?.value || '';
    
    // Wait for DOM to be fully ready
    await new Promise(resolve => setTimeout(resolve, 200));
    
    // ========== GET VALUES FROM WIZARD INPUTS ==========
    // These are the IDs from your step 3 wizard HTML
    const youtubeUrlInput = document.getElementById('youtube-url');
    const youtubeTitleInput = document.getElementById('youtube-title');
    const youtubeThumbInput = document.getElementById('youtube-thumbnail');
    const galleryTitleInput = document.getElementById('gallery-title');
    const mapAddressInput = document.getElementById('map-address');
    const mapLatInput = document.getElementById('map-latitude');
    const mapLngInput = document.getElementById('map-longitude');
    
    // Get values - THESE ARE THE CRITICAL VALUES
    let youtubeUrlValue = youtubeUrlInput ? youtubeUrlInput.value.trim() : '';
    let youtubeTitleValue = youtubeTitleInput && youtubeTitleInput.value ? youtubeTitleInput.value.trim() : 'Property Tour';
    let youtubeThumbnailValue = youtubeThumbInput && youtubeThumbInput.value ? youtubeThumbInput.value.trim() : '';
    let galleryTitleValue = galleryTitleInput && galleryTitleInput.value ? galleryTitleInput.value.trim() : 'Property Gallery';
    // Send the raw values exactly as typed. Empty fields stay empty so the PHP
    // importer never mistakes a blank input for an intentional override and can
    // preserve each demo property's own map data.
    let mapAddressValue = mapAddressInput && mapAddressInput.value ? mapAddressInput.value.trim() : '';
    let mapLatValue = mapLatInput && mapLatInput.value ? mapLatInput.value.trim() : '';
    let mapLngValue = mapLngInput && mapLngInput.value ? mapLngInput.value.trim() : '';

    // True only when the user actually interacted with the Location step. The
    // runtime flag is the primary signal; a non-empty field is treated as a
    // safety-net fallback. Drives the import decision: false => keep demo map;
    // true => override the user-changed fields.
    const locationModified = hvnlyLocationStepModified
        || !!(mapAddressValue || mapLatValue || mapLngValue);

    // No value-based fallback here on purpose: a coordinate's source cannot be
    // inferred from its value. When uncertain we default to "not user-entered",
    // which is the non-destructive choice (demo coordinates are preserved).
    const coordinatesUserEntered = hvnlyCoordinatesUserEntered;
    
    // Extract YouTube video ID from whatever URL the user entered.
    // If the field is empty we leave both values blank — the PHP importer
    // will simply skip the video section for that property.
    let youtubeVideoId = '';
    if (youtubeUrlValue) {
        const patterns = [
            /(?:youtube\.com\/watch\?v=)([^&]+)/,
            /(?:youtu\.be\/)([^?]+)/,
            /(?:youtube\.com\/embed\/)([^?]+)/
        ];
        for (const pattern of patterns) {
            const match = youtubeUrlValue.match(pattern);
            if (match && match[1]) { youtubeVideoId = match[1]; break; }
        }
        // Bare 11-character video ID without a full URL
        if (!youtubeVideoId && youtubeUrlValue.length === 11 && /^[a-zA-Z0-9_-]+$/.test(youtubeUrlValue)) {
            youtubeVideoId = youtubeUrlValue;
            youtubeUrlValue = `https://www.youtube.com/watch?v=${youtubeVideoId}`;
        }
    }

    const importData = {
        total_to_import: propertyImportQuantity,
        include_images: document.getElementById('include-images')?.checked ? 1 : 0,
        email_notifications: document.getElementById('email-notifications')?.checked ? 1 : 0,
        enable_cache_after_import: document.getElementById('enable-cache-after-import')?.checked ? 1 : 0,
        selected_department: selectedDepartment,
        map_provider: selectedMapProvider,
        // Map values
        location_modified: locationModified ? 1 : 0,
        coordinates_user_entered: coordinatesUserEntered ? 1 : 0,
        map_address: mapAddressValue,
        map_latitude: mapLatValue,
        map_longitude: mapLngValue,
        // Video values
        youtube_title: youtubeTitleValue,
        youtube_url: youtubeUrlValue,
        youtube_video_id: youtubeVideoId,
        youtube_thumbnail: youtubeThumbnailValue,
        // Gallery values
        gallery_title: galleryTitleValue,
        // Address values (for Address & Neighborhood section).
        // Split the map address string into its components where possible.
        // Format expected: "Street, City, State ZipCode" or just "City, State".
        full_address: mapAddressValue,
        address_line_1: (function() {
            const parts = mapAddressValue.split(',').map(p => p.trim()).filter(Boolean);
            // If we have 3+ parts the first is a street address, otherwise use the whole string.
            return parts.length >= 3 ? parts[0] : mapAddressValue;
        })(),
        town_city: (function() {
            const parts = mapAddressValue.split(',').map(p => p.trim()).filter(Boolean);
            if (parts.length >= 2) return parts[parts.length - 2].replace(/\s+\d{5}.*$/, '').trim();
            return '';
        })(),
        country_state: (function() {
            const parts = mapAddressValue.split(',').map(p => p.trim()).filter(Boolean);
            if (parts.length >= 2) {
                const last = parts[parts.length - 1];
                // Strip a trailing ZIP code (5 digits) if present.
                return last.replace(/\s+\d{5}(-\d{4})?$/, '').trim();
            }
            return '';
        })(),
        zip_code: (function() {
            const match = mapAddressValue.match(/\b(\d{5})(?:-\d{4})?\b/);
            return match ? match[1] : '';
        })(),
        reference_number: 'REF-' + Date.now(),
        building_number: '1',
        street: '',
        // Departments configuration
        departments: {}
    };
    
    // Add ALL departments
    for (const dept of allDepartments) {
        importData.departments[dept] = getDepartmentConfig(dept);
    }
    
    showImportLoader(propertyImportQuantity);

    setTimeout(addCancellationButtons, 100);
    currentImportSessionId = '';
    window.importBatch(0, 0, importData, csrfToken, 0, '', true);
}

function formatElapsedTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function setImportStage(stageKey) {
    const stages = document.querySelectorAll('#importStages [data-stage]');
    let targetIndex = IMPORT_STAGE_ORDER.indexOf(stageKey);
    if (targetIndex < 0) {
        targetIndex = highestImportStageIndex;
        stageKey = IMPORT_STAGE_ORDER[targetIndex] || 'prepare';
    }

    // Forward-only: never reopen an earlier stage after progress.
    if (targetIndex < highestImportStageIndex) {
        targetIndex = highestImportStageIndex;
        stageKey = IMPORT_STAGE_ORDER[targetIndex];
    } else {
        highestImportStageIndex = targetIndex;
    }

    let reached = false;
    stages.forEach((item) => {
        const key = item.getAttribute('data-stage');
        item.classList.remove('is-active', 'is-done');
        if (key === stageKey) {
            item.classList.add('is-active');
            reached = true;
        } else if (!reached) {
            item.classList.add('is-done');
        }
    });
}

function inferImportStage(percent, message) {
    const text = (message || '').toLowerCase();
    if (percent >= 100 || text.includes('complete')) {
        return 'complete';
    }
    if (text.includes('image') || text.includes('media') || text.includes('gallery')) {
        return 'images';
    }
    if (text.includes('section') || text.includes('field') || text.includes('meta')) {
        return 'sections';
    }
    // Prep / bootstrap responses (percent often 0) must stay on prepare.
    // Do not treat "property builder" as the Creating Properties stage.
    if (
        text.includes('initializ')
        || text.includes('agent')
        || text.includes('agenc')
        || text.includes('prepar')
        || text.includes('builder')
    ) {
        return 'prepare';
    }
    if (text.includes('imported') || text.includes('resum') || text.includes('propert') || percent > 0) {
        return 'properties';
    }
    return 'prepare';
}

function startImportElapsedTimer() {
    importStartedAt = Date.now();
    if (importElapsedTimer) clearInterval(importElapsedTimer);
    importElapsedTimer = setInterval(() => {
        const elapsed = document.getElementById('importElapsedTime');
        if (elapsed && importStartedAt) {
            elapsed.textContent = formatElapsedTime(Date.now() - importStartedAt);
        }
    }, 1000);
}

function stopImportElapsedTimer() {
    if (importElapsedTimer) {
        clearInterval(importElapsedTimer);
        importElapsedTimer = null;
    }
}

/**
 * Switch between 4-step setup wizard and dedicated import progress/completion views.
 *
 * @param {'setup'|'importing'|'complete'} phase
 */
function setWizardPhase(phase) {
    const setupPhase = document.getElementById('hvnly-wizard-setup-phase');
    const importPhase = document.getElementById('hvnly-import-phase');
    const loader = document.getElementById('import-loader');
    const resultView = document.getElementById('import-result-view');
    const isSetup = phase === 'setup';

    document.body.classList.toggle('hvnly-import-phase-active', !isSetup);

    setPanelHidden(setupPhase, !isSetup);
    setPanelHidden(importPhase, isSetup);
    setPanelHidden(loader, phase !== 'importing');
    setPanelHidden(resultView, phase !== 'complete');
}

function showImportLoader(quantity, resetStage = true) {
    setWizardPhase('importing');

    const title = document.getElementById('importLoaderTitle');
    const message = document.getElementById('importMessage');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    if (title) title.textContent = 'Importing Properties…';
    if (message) message.textContent = `Preparing to import ${quantity} properties. Please wait…`;
    if (progressFill) progressFill.style.width = '0%';
    if (progressText) progressText.textContent = '0%';
    if (resetStage) {
        highestImportStageIndex = 0;
        setImportStage('prepare');
    }
    startImportElapsedTimer();
}

function hideImportLoader() {
    stopImportElapsedTimer();
    setWizardPhase('setup');
}

function showImportResultView(html) {
    stopImportElapsedTimer();

    const resultView = document.getElementById('import-result-view');
    if (resultView) {
        resultView.innerHTML = html;
    }

    setWizardPhase('complete');
}

function hideImportResultView() {
    const resultView = document.getElementById('import-result-view');
    if (resultView) {
        resultView.innerHTML = '';
    }
}

function updateProgress(data) {
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const importMessage = document.getElementById('importMessage');
    const percent = Math.min(100, Math.max(0, parseInt(data.percent, 10) || 0));
    if (progressFill) progressFill.style.width = percent + '%';
    if (progressText) progressText.textContent = percent + '%';
    if (importMessage && data.message) importMessage.textContent = data.message;
    setImportStage(inferImportStage(percent, data.message));
    if (!document.getElementById('cancelImportBtn') && isImporting && !importCancelled) addCancellationButtons();
}

function setCelebrationButtonLabel(button, label) {
    if (!button || !label) {
        return;
    }

    const labelEl = button.querySelector('.hvnly-celebration-btn-label');
    if (labelEl) {
        labelEl.textContent = label;
        return;
    }

    button.textContent = label;
}

function showCelebration(importedCount) {
    isImporting = false;
    stopImportElapsedTimer();
    setImportStage('complete');
    hideImportLoader();

    const count = Math.max(0, parseInt(importedCount, 10) || 0);
    const countLabel = count === 1
        ? '1 property'
        : `${count} properties`;

    const resultView = document.getElementById('import-result-view');
    if (resultView) {
        resultView.innerHTML = '';
        resultView.hidden = true;
    }

    const setupPhase = document.getElementById('hvnly-wizard-setup-phase');
    const importPhase = document.getElementById('hvnly-import-phase');
    setPanelHidden(setupPhase, true);
    setPanelHidden(importPhase, true);

    const celebration = document.getElementById('celebrationModal');
    const celebrationTitle = celebration?.querySelector('.hvnly--property--import-celebration-title');
    const celebrationDesc = celebration?.querySelector('.hvnly--property--import-celebration-description');
    const viewSiteBtn = document.getElementById('celebration-view-site');
    const dashboardBtn = document.getElementById('celebration-dashboard');
    const successUrls = hvnlyImportWizard.successUrls || {};

    if (celebrationTitle) {
        celebrationTitle.textContent = hvnlyImportWizard.strings?.complete || 'Import complete!';
    }

    if (celebrationDesc && count > 0) {
        celebrationDesc.textContent = `Successfully imported ${countLabel}. Your listings, taxonomies, and demo content are ready.`;
    }

    if (viewSiteBtn && successUrls.viewUrl) {
        viewSiteBtn.href = successUrls.viewUrl;
        setCelebrationButtonLabel(
            viewSiteBtn,
            successUrls.viewLabel || hvnlyImportWizard.strings?.viewProperties || 'View Properties'
        );
    }

    if (dashboardBtn && successUrls.dashboardUrl) {
        dashboardBtn.href = successUrls.dashboardUrl;
        setCelebrationButtonLabel(dashboardBtn, successUrls.dashboardLabel || 'Return to Dashboard');
    }

    if (celebration) {
        celebration.classList.add('active');
    }
}

function createCancelButton() {
    return `<div class="hvnly--property--import-cancel-container"><button class="hvnly--property--import-cancel-button" id="cancelImportBtn"><i class="fas fa-stop-circle"></i> ${hvnlyImportWizard.strings.cancel}</button><button class="hvnly--property--import-pause-button" id="pauseImportBtn"><i class="fas fa-pause-circle"></i> ${hvnlyImportWizard.strings.pause}</button></div>`;
}

function createErrorPopup(message, details, showResume) {
    const resumeButton = showResume
        ? '<button type="button" class="hvnly--property--import-completion-button secondary" id="resumeImportErrorBtn">Resume Import</button>'
        : '';
    return `<div class="hvnly--property--import-error-popup"><div><i class="fas fa-exclamation-triangle"></i><h3>Import Error</h3></div><p>${escapeHtml(message)}</p>${details ? `<p><small>${escapeHtml(details)}</small></p>` : ''}<div class="hvnly--property--import-completion-actions">${resumeButton}<button type="button" id="closeErrorBtn">Close</button></div></div><div class="hvnly--property--import-error-overlay"></div>`;
}

function showErrorPopup(message, details, showResume) {
    document.querySelectorAll('.hvnly--property--import-error-popup, .hvnly--property--import-error-overlay').forEach(el => el.remove());
    document.body.insertAdjacentHTML('beforeend', createErrorPopup(message, details, showResume));
    document.getElementById('closeErrorBtn')?.addEventListener('click', () => {
        document.querySelectorAll('.hvnly--property--import-error-popup, .hvnly--property--import-error-overlay').forEach(el => el.remove());
        isImporting = false;
        hideImportLoader();
        hideImportResultView();
        // Keep interrupted session context so Skip/Complete resume instead of starting duplicates.
        currentStep = 4;
        showStep(4);
        if (showResume && (currentImportSessionId || window.hvnlyImportWizard?.importResume?.can_resume)) {
            setupImportResumePrompt();
        }
    });
    document.getElementById('resumeImportErrorBtn')?.addEventListener('click', () => {
        document.querySelectorAll('.hvnly--property--import-error-popup, .hvnly--property--import-error-overlay').forEach(el => el.remove());
        resumeInterruptedImport();
    });
}

function resumeInterruptedImport() {
    const importData = currentImportData || window.hvnlyImportWizard?.importResume?.import_options;
    const sessionId = currentImportSessionId || window.hvnlyImportWizard?.importResume?.session_id || '';
    const csrfToken = currentCsrfToken || document.getElementById('hvnly_csrf_token')?.value || '';

    if (!importData || !sessionId || !csrfToken) {
        return;
    }

    if (importRequestInFlight) {
        return;
    }

    isImporting = true;
    importCancelled = false;
    importPaused = false;
    currentImportSessionId = sessionId;
    currentImportData = importData;

    const resume = window.hvnlyImportWizard?.importResume || {};
    const imported = typeof currentImported === 'number' ? currentImported : (parseInt(resume.imported, 10) || 0);
    const batch = typeof currentBatch === 'number' ? currentBatch : (parseInt(resume.current_batch, 10) || imported);
    const total = parseInt(importData.total_to_import, 10) || parseInt(resume.total_to_import, 10) || propertyImportQuantity;

    currentImported = imported;
    currentBatch = batch;
    importData.total_to_import = total;

    hideImportResultView();
    showImportLoader(total, false);
    updateProgress({
        imported,
        total,
        percent: total > 0 ? Math.round((imported / total) * 100) : 0,
        message: `Resuming import at ${imported} of ${total} properties…`,
    });
    setTimeout(addCancellationButtons, 100);
    window.importBatch(batch, imported, importData, csrfToken, 0, sessionId, false);
}

function setupImportResumePrompt() {
    const resume = window.hvnlyImportWizard?.importResume;
    if (!resume?.can_resume || !resume.import_options) {
        return;
    }

    const host = document.querySelector('.hvnly--property--import-wizard') || document.querySelector('.wrap');
    if (!host || document.getElementById('hvnlyImportResumeBanner')) {
        return;
    }

    const banner = document.createElement('div');
    banner.id = 'hvnlyImportResumeBanner';
    banner.className = 'notice notice-warning';
    banner.style.margin = '16px 0';
    banner.innerHTML = `<p><strong>Interrupted import detected.</strong> ${resume.imported} of ${resume.total_to_import} properties were imported. <button type="button" class="button button-primary" id="hvnlyImportResumeBannerBtn">Resume Import</button></p>`;
    host.prepend(banner);

    document.getElementById('hvnlyImportResumeBannerBtn')?.addEventListener('click', () => {
        currentImportSessionId = resume.session_id;
        currentImported = parseInt(resume.imported, 10) || 0;
        currentBatch = parseInt(resume.current_batch, 10) || currentImported;
        currentImportData = Object.assign({}, resume.import_options, { total_to_import: resume.total_to_import });
        currentCsrfToken = document.getElementById('hvnly_csrf_token')?.value || '';
        resumeInterruptedImport();
    });
}

function handleImportError(msg, details, importData, csrfToken) {
    isImporting = false;
    const message = typeof msg === 'string' ? msg : (msg?.message || 'Import error');
    const canResume = !!(currentImportSessionId || window.hvnlyImportWizard?.importResume?.can_resume);
    showErrorPopup(message, details, canResume);
}

function handleImportCancellation() {
    isImporting = false;
    importCancelled = true;
    importRequestInFlight = false;
    currentImportXhr = null;
    if (pausePollTimer) {
        clearTimeout(pausePollTimer);
        pausePollTimer = null;
    }
    stopImportElapsedTimer();
    showImportResultView(`<div class="hvnly--property--import-completion-screen"><div class="hvnly--property--import-completion-icon" style="background:var(--hvnly-brand-warning);"><i class="fas fa-stop-circle"></i></div><h2 class="hvnly--property--import-completion-title">Import Cancelled</h2><p>Imported ${currentImported} of ${currentImportData?.total_to_import || 0} properties.</p><div class="hvnly--property--import-completion-actions"><a href="edit.php?post_type=hvnly_property" class="hvnly--property--import-completion-button primary">View Properties</a><button class="hvnly--property--import-completion-button secondary" id="resumeImportBtn">Resume</button><button class="hvnly--property--import-completion-button secondary" id="restartImportBtn">Start Over</button></div></div>`);
    document.getElementById('resumeImportBtn')?.addEventListener('click', () => {
        importCancelled = false;
        importPaused = false;
        hideImportResultView();
        resumeInterruptedImport();
    });
    document.getElementById('restartImportBtn')?.addEventListener('click', () => {
        importCancelled = false;
        importPaused = false;
        hideImportResultView();
        // If properties were already created, keep session so Complete/Skip resume
        // instead of creating a duplicate import.
        if (!(currentImported > 0 || window.hvnlyImportWizard?.importResume?.can_resume)) {
            currentImportSessionId = '';
            currentImported = 0;
            currentBatch = 0;
            currentImportData = null;
        }
        setWizardPhase('setup');
        currentStep = 1;
        showStep(1);
    });
}

function addCancellationButtons() {
    const progressContainer = document.querySelector('.hvnly--property--import-progress-container');
    if (progressContainer && !document.getElementById('cancelImportBtn')) {
        progressContainer.insertAdjacentHTML('afterend', createCancelButton());
        document.getElementById('cancelImportBtn')?.addEventListener('click', () => {
            if (confirm(hvnlyImportWizard.strings.cancelConfirm)) {
                importCancelled = true;
                importPaused = false;
                if (pausePollTimer) {
                    clearTimeout(pausePollTimer);
                    pausePollTimer = null;
                }
                if (currentImportXhr && typeof currentImportXhr.abort === 'function') {
                    try {
                        currentImportXhr.abort();
                    } catch (e) {
                        // Ignore abort errors.
                    }
                }
                jQuery.ajax({
                    url: hvnlyImportWizard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hvnly_cancel_import',
                        nonce: hvnlyImportWizard.nonce,
                        csrf_token: currentCsrfToken
                    },
                    complete: () => handleImportCancellation()
                });
            }
        });
        document.getElementById('pauseImportBtn')?.addEventListener('click', function() {
            if (importPaused) {
                importPaused = false;
                this.innerHTML = '<i class="fas fa-pause-circle"></i> Pause';
                if (pausePollTimer) {
                    clearTimeout(pausePollTimer);
                    pausePollTimer = null;
                }
                if (!importCancelled && !importRequestInFlight) {
                    window.importBatch(currentBatch, currentImported, currentImportData, currentCsrfToken, 0, currentImportSessionId, false);
                }
            } else {
                importPaused = true;
                this.innerHTML = '<i class="fas fa-play-circle"></i> Resume';
                const importMessage = document.getElementById('importMessage');
                if (importMessage && importRequestInFlight) {
                    importMessage.textContent = 'Pausing after the current property finishes…';
                }
            }
        });
    }
}

// =========================================
// URL STEP ROUTING
// =========================================

function getWizardStepSlugFromUrl() {
    return new URLSearchParams(window.location.search).get('step');
}

function resolveStepNumberFromSlug(slug) {
    if (!slug || typeof slug !== 'string') {
        return null;
    }
    const normalized = slug.trim().toLowerCase();
    if (Object.prototype.hasOwnProperty.call(WIZARD_SLUG_TO_STEP, normalized)) {
        return WIZARD_SLUG_TO_STEP[normalized];
    }
    return null;
}

function resolveInitialWizardStep() {
    const slug = getWizardStepSlugFromUrl();
    if (!slug) {
        return 1;
    }
    const step = resolveStepNumberFromSlug(slug);
    return step === null ? 1 : step;
}

function stepNumberToSlug(stepNumber) {
    return WIZARD_STEP_SLUGS[stepNumber] || 'department';
}

function buildWizardHistoryState(stepNumber) {
    return {
        hvnlyWizardStep: stepNumber,
        hvnlyWizardSlug: stepNumberToSlug(stepNumber),
    };
}

function syncWizardStepUrl(mode = 'push') {
    if (isImporting) {
        return;
    }

    const slug = stepNumberToSlug(currentStep);
    const url = new URL(window.location.href);
    const currentParam = url.searchParams.get('step');

    if (currentParam) {
        const currentStepFromUrl = resolveStepNumberFromSlug(currentParam);
        if (currentStepFromUrl === currentStep) {
            return;
        }
    } else if (currentParam === slug) {
        return;
    }

    url.searchParams.set('step', slug);
    const nextUrl = url.pathname + url.search + url.hash;
    const state = buildWizardHistoryState(currentStep);

    if (mode === 'replace') {
        history.replaceState(state, '', nextUrl);
    } else {
        history.pushState(state, '', nextUrl);
    }
}

function seedWizardHistoryState(stepNumber) {
    history.replaceState(
        buildWizardHistoryState(stepNumber),
        '',
        window.location.pathname + window.location.search + window.location.hash
    );
}

function setupWizardUrlRouting() {
    if (wizardUrlRoutingBound) {
        return;
    }
    wizardUrlRoutingBound = true;

    window.addEventListener('popstate', (event) => {
        if (isImporting) {
            return;
        }

        let step = null;
        if (event.state && typeof event.state.hvnlyWizardStep === 'number') {
            step = event.state.hvnlyWizardStep;
        }

        if (!step || step < 1 || step > totalSteps) {
            const slug = getWizardStepSlugFromUrl();
            const parsed = slug ? resolveStepNumberFromSlug(slug) : null;
            step = parsed === null ? 1 : parsed;
        }

        showStep(step, { updateHistory: false });
    });
}

function initializeWizardStepFromUrl() {
    const initialSlug = getWizardStepSlugFromUrl();
    const hasInvalidSlug = !!(initialSlug && resolveStepNumberFromSlug(initialSlug) === null);
    const initialStep = resolveInitialWizardStep();

    showStep(initialStep, { updateHistory: false });

    if (hasInvalidSlug || !initialSlug) {
        syncWizardStepUrl('replace');
    } else {
        seedWizardHistoryState(initialStep);
    }
}

// =========================================
// WIZARD ACCORDION UX (presentation only)
// =========================================

function loadWizardAccordionState() {
    try {
        const raw = sessionStorage.getItem(WIZARD_ACCORDION_STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function saveWizardAccordionState(state) {
    try {
        sessionStorage.setItem(WIZARD_ACCORDION_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
        // Session storage unavailable — ignore.
    }
}

function getAccordionStorageKey(panel) {
    const step = panel.closest('[data-wizard-step]')?.getAttribute('data-wizard-step') || '';
    const id = panel.getAttribute('data-accordion-id') || '';
    return `${step}:${id}`;
}

function setAccordionSummary(accordionId, text) {
    const el = document.querySelector(`[data-accordion-summary-for="${accordionId}"]`);
    if (el) {
        el.textContent = text || '';
    }
}

function updateWizardAccordionSummaries() {
    const deptLabel = WIZARD_DEPARTMENT_LABELS[selectedDepartment] || selectedDepartment;
    setAccordionSummary('department-taxonomies', `${deptLabel} · 3 taxonomies configured`);
    setAccordionSummary('property-features', 'auto-configured');

    const provider = document.querySelector('.hvnly--property--import-map-provider.active')?.getAttribute('data-provider')
        || selectedMapProvider
        || 'leaflet';
    const providerLabels = { leaflet: 'Leaflet', openstreetmap: 'OpenStreetMap', google: 'Google Maps' };
    setAccordionSummary('location-settings', providerLabels[provider] || provider);

    if (provider === 'google') {
        const apiKey = document.getElementById('google-api-key')?.value?.trim();
        setAccordionSummary('provider-details', apiKey ? 'API key set' : 'API key required');
    } else {
        setAccordionSummary('provider-details', 'no API key needed');
    }

    const address = document.getElementById('map-address')?.value?.trim();
    const lat = document.getElementById('map-latitude')?.value?.trim();
    const lng = document.getElementById('map-longitude')?.value?.trim();
    if (address) {
        const shortAddress = address.length > 28 ? `${address.slice(0, 28)}…` : address;
        setAccordionSummary('default-location', lat && lng ? `${shortAddress} · custom map` : shortAddress);
    } else {
        setAccordionSummary('default-location', lat && lng ? 'custom map enabled' : '');
    }

    const youtubeUrl = document.getElementById('youtube-url')?.value?.trim();
    setAccordionSummary('video-settings', youtubeUrl ? 'video enabled' : 'no video');

    setAccordionSummary('media-settings', '3 images selected');

    const qty = document.getElementById('propertyQuantitySlider')?.value || propertyImportQuantity;
    setAccordionSummary('import-quantity', `${qty} properties`);

    let optionCount = 0;
    if (document.getElementById('include-images')?.checked) optionCount += 1;
    if (document.getElementById('email-notifications')?.checked) optionCount += 1;
    if (document.getElementById('enable-cache-after-import')?.checked) optionCount += 1;
    setAccordionSummary('import-options', optionCount ? `${optionCount} options on` : 'defaults');

    setAccordionSummary('agent-settings', 'demo agents included');
    setAccordionSummary('additional-content', currentStep === totalSteps ? 'ready to import' : '');
}

function applyWizardAccordionStateForStep(stepNumber) {
    const state = loadWizardAccordionState();
    document.querySelectorAll(`.hvnly-wizard-accordion[data-wizard-step="${stepNumber}"] .hvnly-wizard-accordion__panel`).forEach((panel) => {
        const key = getAccordionStorageKey(panel);
        if (Object.prototype.hasOwnProperty.call(state, key)) {
            panel.open = !!state[key];
        }
    });
    updateWizardAccordionSummaries();
}

function setupWizardAccordions() {
    if (wizardAccordionsBound) {
        updateWizardAccordionSummaries();
        return;
    }
    wizardAccordionsBound = true;

    document.querySelectorAll('.hvnly-wizard-accordion__panel').forEach((panel) => {
        panel.addEventListener('toggle', () => {
            const state = loadWizardAccordionState();
            state[getAccordionStorageKey(panel)] = panel.open;
            saveWizardAccordionState(state);
        });
    });

    const panels = document.getElementById('step-panels');
    if (panels) {
        panels.addEventListener('input', updateWizardAccordionSummaries);
        panels.addEventListener('change', updateWizardAccordionSummaries);
    }

    updateWizardAccordionSummaries();
}

// =========================================
// NAVIGATION
// =========================================

function updateGoogleApiRowVisibility() {
    updateMapProviderConditionalUi();
}

function setupWizardDelegatedEvents() {
    if (wizardEventsBound) return;
    wizardEventsBound = true;

    const panels = document.getElementById('step-panels');
    if (!panels) return;

    panels.addEventListener('click', async (event) => {
        const deptCard = event.target.closest('.hvnly--property--import-department-card');
        if (deptCard) {
            document.querySelectorAll('.hvnly--property--import-department-card').forEach((c) => c.classList.remove('selected'));
            deptCard.classList.add('selected');
            selectedDepartment = deptCard.getAttribute('data-department') || 'sale';
            updateWizardAccordionSummaries();
            return;
        }

        const mapProvider = event.target.closest('.hvnly--property--import-map-provider');
        if (mapProvider) {
            const provider = mapProvider.getAttribute('data-provider') || 'leaflet';
            syncMapProviderUi(provider);

            try {
                const response = await saveMapProvider(provider);
                if (!response?.success) {
                    alert(response?.data?.message || 'Could not save map provider.');
                    return;
                }
                if (response.data?.provider) {
                    syncMapProviderUi(response.data.provider);
                }
            } catch (e) {
                alert('Could not save map provider. Please try again.');
                return;
            }

            if (isStep2Active) {
                await initMap();
            }
            updateWizardAccordionSummaries();
        }
    });

    document.getElementById('save-google-api-key')?.addEventListener('click', async () => {
        const apiKey = document.getElementById('google-api-key')?.value?.trim();
        if (!apiKey) {
            alert('Please enter a Google Maps API key');
            return;
        }
        if (selectedMapProvider !== 'google') {
            return;
        }
        try {
            const response = await saveGoogleApiKey(apiKey);
            if (!response?.success) {
                alert(response?.data?.message || hvnlyImportWizard.strings?.apiKeySaveFailed || 'Could not save API key.');
                return;
            }
            if (isStep2Active) {
                await initMap();
            }
        } catch (e) {
            alert(hvnlyImportWizard.strings?.apiKeySaveFailed || 'Could not save API key.');
        }
    });
}

function setupNavigationButtons() {
    const prev = document.querySelector('.hvnly--property--import-nav-button.prev');
    const next = document.querySelector('.hvnly--property--import-nav-button.next');
    const skip = document.querySelector('.hvnly--property--import-nav-button.skip');

    if (prev) {
        prev.addEventListener('click', () => {
            if (currentStep > 1 && !isImporting) {
                showStep(currentStep - 1);
            }
        });
    }
    if (next) {
        next.addEventListener('click', () => {
            if (currentStep < totalSteps && !isImporting) {
                showStep(currentStep + 1);
            } else if (currentStep === totalSteps && !isImporting) {
                startOrResumeImportFromWizard();
            }
        });
    }
    if (skip) {
        skip.addEventListener('click', () => {
            if (confirm('Skip this step?') && !isImporting) {
                if (currentStep < totalSteps) {
                    showStep(currentStep + 1);
                } else {
                    // Final step: never force a brand-new session when one can be resumed.
                    startOrResumeImportFromWizard();
                }
            }
        });
    }
}

function updateStepProgress() {
    const stepper = document.getElementById('hvnlyWizardStepper');
    const items = document.querySelectorAll('.hvnly-wizard-stepper__item[data-step]');

    items.forEach((item) => {
        const step = parseInt(item.getAttribute('data-step'), 10);
        item.classList.remove('is-active', 'is-completed', 'active', 'completed');

        if (step < currentStep) {
            item.classList.add('is-completed', 'completed');
        } else if (step === currentStep) {
            item.classList.add('is-active', 'active');
        }
    });

    if (stepper) {
        stepper.setAttribute('data-current-step', String(currentStep));
    }

    const counter = document.getElementById('hvnlyWizardStepCounter');
    if (counter) {
        counter.textContent = String(currentStep);
    }

    const bar = document.getElementById('stepProgressBar');
    if (bar && totalSteps > 1) {
        const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
        bar.style.width = `${progress}%`;
    }
}

function updateNextButtonLabel() {
    const nextBtn = document.querySelector('.hvnly--property--import-nav-button.next');
    if (nextBtn) {
        nextBtn.innerHTML = currentStep === totalSteps
            ? 'Complete <i class="fas fa-check"></i>'
            : 'Next <i class="fas fa-arrow-right"></i>';
    }
}

function showStep(step, options = {}) {
    const updateHistory = options.updateHistory !== false;
    const historyMode = options.historyMode || 'push';
    const stepNumber = Math.min(totalSteps, Math.max(1, parseInt(step, 10) || 1));

    if (currentStep === 2 && stepNumber !== 2) {
        isStep2Active = false;
        destroyMap();
    }

    currentStep = stepNumber;

    document.querySelectorAll('.hvnly-step-panel').forEach((panel) => {
        const panelStep = parseInt(panel.getAttribute('data-step'), 10);
        if (panelStep === stepNumber) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }
    });

    updateStepProgress();
    updateNextButtonLabel();
    updateGoogleApiRowVisibility();

    if (stepNumber === 2) {
        isStep2Active = true;
        setupAddressAutocomplete();
        initMap();
    } else if (stepNumber === 3) {
        initYouTubePreview();
    } else if (stepNumber === 4) {
        setupQuantitySlider();
    }

    applyWizardAccordionStateForStep(stepNumber);

    if (updateHistory) {
        syncWizardStepUrl(historyMode);
    }
}

function setupCacheImportToggle() {
    const toggle = document.getElementById('enable-cache-after-import');
    const status = document.getElementById('cache-import-status');
    if (!toggle || !status) {
        return;
    }

    const strings = window.hvnlyImportWizard?.strings || {};
    const statusOn = strings.cacheStatusOn || 'Status: ON — listings (grid, list, map, sidebar) use cached AJAX responses.';
    const statusOff = strings.cacheStatusOff || 'Status: OFF — listings use live queries without cache.';

    if (typeof window.hvnlyImportWizard?.cacheEnabled === 'boolean') {
        toggle.checked = window.hvnlyImportWizard.cacheEnabled;
    }

    const updateStatus = () => {
        status.textContent = toggle.checked ? statusOn : statusOff;
    };

    toggle.addEventListener('change', updateStatus);
    updateStatus();
}

function setupQuantitySlider() {
    const slider = document.getElementById('propertyQuantitySlider');
    const value = document.getElementById('quantityValue');
    const maxLimit = window.hvnlyImportWizard?.maxImportLimit || 50;
    if (!slider || !value) return;

    slider.max = maxLimit;
    slider.value = Math.min(propertyImportQuantity, maxLimit);
    value.textContent = slider.value;

    if (!quantitySliderBound) {
        quantitySliderBound = true;
        slider.addEventListener('input', function () {
            propertyImportQuantity = Math.min(parseInt(this.value, 10), maxLimit);
            value.textContent = propertyImportQuantity;
            updateWizardAccordionSummaries();
        });
    }
}

// =========================================
// DOM READY
// =========================================

document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('hvnly-wizard-ready');
    syncMapProviderUi(selectedMapProvider);
    setupWizardUrlRouting();
    initializeWizardStepFromUrl();
    setupWizardDelegatedEvents();
    setupNavigationButtons();
    setupWizardAccordions();
    setupQuantitySlider();
    setupCacheImportToggle();
    setupImportResumePrompt();

    // Track genuine user edits on the Location fields. Programmatic updates from
    // the map manager (input.value = ...) do not dispatch 'input', so these only
    // fire on real typing.
    //
    // Typing in the address field marks the step modified but leaves coordinates
    // as system-generated (address-only override). Typing directly into latitude
    // or longitude marks the coordinates as user-entered (full override) — this
    // is essential because the map manager's own coordinate sync only fires when
    // BOTH coordinates are valid, so a single-field coordinate edit would
    // otherwise go untracked.
    const addressField = document.getElementById('map-address');
    if (addressField) {
        addressField.addEventListener('input', function () {
            window.hvnlyImportWizardMarkLocationModified();
        });
    }
    ['map-latitude', 'map-longitude'].forEach((id) => {
        const field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', function () {
                window.hvnlyImportWizardMarkLocationModified('manual');
            });
        }
    });

    const closeButton = document.querySelector('.hvnly--property--import-close-button');
    if (closeButton) {
        closeButton.addEventListener('click', (e) => { e.preventDefault(); if (confirm('Close wizard?')) window.location.href = 'edit.php?post_type=hvnly_property'; });
    }

    document.getElementById('celebration-dashboard')?.addEventListener('click', () => {
        document.getElementById('celebrationModal')?.classList.remove('active');
    });
    
    const docButton = document.querySelector('.hvnly--property--import-doc-button');
    if (docButton) {
        docButton.addEventListener('click', (e) => { e.preventDefault(); window.open('https://havenlytics.com/documentation/', '_blank'); });
    }
});