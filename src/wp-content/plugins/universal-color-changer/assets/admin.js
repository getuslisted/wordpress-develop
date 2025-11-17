(function() {
const { createElement, render, useEffect, useState } = wp.element;
const apiFetch = wp.apiFetch;

function parseCanonicalColor( color ) {
const parts = String( color || '' ).split( '@' );
return { base: parts[0] || '', alpha: parts[1] || null };
}

function formatCanonicalColor( color ) {
const parsed = parseCanonicalColor( color );
if ( parsed.alpha && parsed.alpha !== '1' ) {
return parsed.base + ' (α ' + parsed.alpha + ')';
}

return parsed.base;
}

if ( typeof uccAdmin !== 'undefined' && uccAdmin.nonce ) {
	apiFetch.use( wp.apiFetch.createNonceMiddleware( uccAdmin.nonce ) );
}

function ProgressBar( { value, max } ) {
const percent = max > 0 ? Math.round( ( value / max ) * 100 ) : 0;
return createElement( 'div', { className: 'ucc-progress' },
createElement( 'div', { className: 'ucc-progress__bar', style: { width: percent + '%' } }, percent + '%' )
);
}

function Dashboard( { status, onIndex, indexing, config, onConfigChange } ) {
return createElement( 'div', { className: 'ucc-section' },
createElement( 'p', null, uccAdmin.i18n.description ),
createElement( 'div', { className: 'ucc-stats' },
createElement( 'div', null, createElement( 'strong', null, status.occurrence_count || 0 ), createElement( 'span', null, ' ', uccAdmin.i18n.occurrencesLabel ) ),
createElement( 'div', null, createElement( 'strong', null, status.color_count || 0 ), createElement( 'span', null, ' ', uccAdmin.i18n.colorsLabel ) ),
createElement( 'div', null, createElement( 'strong', null, status.last_indexed_at || '—' ), createElement( 'span', null, ' ', uccAdmin.i18n.lastIndexedLabel ) )
),
createElement( 'div', { className: 'ucc-config' },
createElement( 'label', null,
createElement( 'input', {
type: 'checkbox',
checked: config.scan_titles,
onChange: ( event ) => onConfigChange( { scan_titles: event.target.checked } ),
} ),
' ', uccAdmin.i18n.scanTitles
),
createElement( 'label', null,
createElement( 'input', {
type: 'checkbox',
checked: config.scan_excerpts,
onChange: ( event ) => onConfigChange( { scan_excerpts: event.target.checked } ),
} ),
' ', uccAdmin.i18n.scanExcerpts
),
createElement( 'label', null,
uccAdmin.i18n.postMetaMode,
createElement( 'select', {
value: config.postmeta_mode,
onChange: ( event ) => onConfigChange( { postmeta_mode: event.target.value } ),
},
createElement( 'option', { value: 'allowlist' }, uccAdmin.i18n.postMetaAllowlist ),
createElement( 'option', { value: 'all' }, uccAdmin.i18n.postMetaAll )
)
)
),
createElement( 'button', {
className: 'button button-primary',
onClick: () => onIndex( true ),
disabled: indexing,
}, indexing ? uccAdmin.i18n.indexing : uccAdmin.i18n.indexButton ),
indexing ? createElement( 'p', null, uccAdmin.i18n.indexingProgress ) : null
);
}

function ColorsView( { colors, onSelect, selectedColor, items } ) {
const label = selectedColor ? formatCanonicalColor( selectedColor ) : null;
return createElement( 'div', { className: 'ucc-section' },
createElement( 'h2', null, uccAdmin.i18n.byColor ),
createElement( 'div', { className: 'ucc-grid' },
createElement( 'div', null,
createElement( 'ul', { className: 'ucc-color-list' },
colors.map( ( color ) => {
const parsed = parseCanonicalColor( color.color );
return createElement( 'li', {
key: color.color,
className: selectedColor === color.color ? 'is-active' : '',
onClick: () => onSelect( color.color ),
}, createElement( 'span', { className: 'ucc-swatch', style: { backgroundColor: parsed.base } } ), formatCanonicalColor( color.color ), ' (', color.count, ')' );
} )
)
),
createElement( 'div', { className: 'ucc-color-items' },
selectedColor ? createElement( 'div', null,
createElement( 'h3', null, label ),
createElement( 'ul', null,
items.map( ( item, index ) => createElement( 'li', { key: index }, item.object_type, ' #', item.object_id, ' → ', item.field, createElement( 'pre', null, item.context_excerpt || '' ) ) )
)
) : createElement( 'p', null, uccAdmin.i18n.noData )
)
)
);
}

function PostView( { onFetch, results } ) {
const [ postId, setPostId ] = useState( '' );
return createElement( 'div', { className: 'ucc-section' },
createElement( 'h2', null, uccAdmin.i18n.byPost ),
createElement( 'div', { className: 'ucc-form-row' },
createElement( 'input', {
type: 'number',
value: postId,
onChange: ( event ) => setPostId( event.target.value ),
placeholder: uccAdmin.i18n.postIdPlaceholder,
} ),
createElement( 'button', {
className: 'button',
onClick: () => onFetch( postId ),
disabled: ! postId,
}, uccAdmin.i18n.fetch )
),
results.length ? createElement( 'ul', null, results.map( ( item ) => {
const parsed = parseCanonicalColor( item.color );
return createElement( 'li', { key: item.color },
createElement( 'span', { className: 'ucc-swatch', style: { backgroundColor: parsed.base } } ), ' ', formatCanonicalColor( item.color ), ' (', item.count, ')' );
} ) ) : createElement( 'p', null, uccAdmin.i18n.noData )
);
}

function HistoryView( { history, onUndo, onUndoAll } ) {
return createElement( 'div', { className: 'ucc-section' },
createElement( 'h2', null, uccAdmin.i18n.history ),
history.length ? createElement( 'table', { className: 'widefat striped' },
createElement( 'thead', null,
createElement( 'tr', null,
createElement( 'th', null, uccAdmin.i18n.historyId ),
createElement( 'th', null, uccAdmin.i18n.historySource ),
createElement( 'th', null, uccAdmin.i18n.historyTarget ),
createElement( 'th', null, uccAdmin.i18n.historyItems ),
createElement( 'th', null, uccAdmin.i18n.historyStatus ),
createElement( 'th', null, uccAdmin.i18n.historyActions )
)
),
createElement( 'tbody', null,
history.map( ( row ) => createElement( 'tr', { key: row.id },
createElement( 'td', null, row.id ),
createElement( 'td', null, formatCanonicalColor( row.source_color ) ),
createElement( 'td', null, row.target_color ),
createElement( 'td', null, row.item_count ),
createElement( 'td', null, row.status ),
createElement( 'td', null,
createElement( 'button', {
className: 'button',
onClick: () => onUndo( row.id ),
disabled: 'undone' === row.status,
}, uccAdmin.i18n.undo )
)
)
)
),
createElement( 'button', { className: 'button button-secondary', onClick: onUndoAll }, uccAdmin.i18n.undoAll )
 ) : createElement( 'p', null, uccAdmin.i18n.noData )
);
}

function ReplacementView( { onDryRun, onApply, dryRun, progress, setSource, setTarget, source, target } ) {
return createElement( 'div', { className: 'ucc-section' },
createElement( 'h2', null, uccAdmin.i18n.replacement ),
createElement( 'div', { className: 'ucc-form-row' },
createElement( 'input', {
type: 'text',
value: source,
onChange: ( event ) => setSource( event.target.value ),
placeholder: uccAdmin.i18n.sourcePlaceholder,
} ),
createElement( 'input', {
type: 'text',
value: target,
onChange: ( event ) => setTarget( event.target.value ),
placeholder: uccAdmin.i18n.targetPlaceholder,
} ),
createElement( 'button', { className: 'button', onClick: onDryRun }, uccAdmin.i18n.dryRun )
),
dryRun ? createElement( 'div', { className: 'ucc-dry-run' },
createElement( 'p', null, uccAdmin.i18n.replacementMatches, ' ', dryRun.total ),
createElement( 'ul', null, dryRun.fields.map( ( field, index ) => createElement( 'li', { key: index }, field.object_type, ' #', field.object_id, ' → ', field.field, ' (', field.occurrence_count, ')' ) ) ),
createElement( 'button', { className: 'button button-primary', onClick: onApply }, uccAdmin.i18n.apply )
) : null,
progress ? createElement( 'div', { className: 'ucc-progress-wrapper' },
createElement( ProgressBar, { value: progress.processed, max: progress.processed + progress.remaining } ),
createElement( 'p', null, uccAdmin.i18n.replacementRemaining, ' ', progress.remaining )
) : null
);
}

function App() {
const [ status, setStatus ] = useState( { occurrence_count: 0, color_count: 0, history: [] } );
const [ colors, setColors ] = useState( [] );
const [ selectedColor, setSelectedColor ] = useState( null );
const [ colorItems, setColorItems ] = useState( [] );
const [ postColors, setPostColors ] = useState( [] );
const [ tab, setTab ] = useState( 'dashboard' );
const [ indexing, setIndexing ] = useState( false );
const [ config, setConfig ] = useState( { scan_titles: false, scan_excerpts: false, postmeta_mode: 'allowlist' } );
const [ dryRun, setDryRun ] = useState( null );
const [ source, setSource ] = useState( '' );
const [ target, setTarget ] = useState( '' );
const [ progress, setProgress ] = useState( null );

const refreshStatus = () => {
apiFetch( { path: uccAdmin.root + '/status' } ).then( ( data ) => {
setStatus( data );
setConfig( {
scan_titles: !! data.scan_titles,
scan_excerpts: !! data.scan_excerpts,
postmeta_mode: data.postmeta_mode || 'allowlist',
} );
} );
};

useEffect( () => {
refreshStatus();
apiFetch( { path: uccAdmin.root + '/colors' } ).then( setColors );
}, [] );

useEffect( () => {
if ( selectedColor ) {
apiFetch( { path: uccAdmin.root + '/by-color?color=' + encodeURIComponent( selectedColor ) } ).then( setColorItems );
}
}, [ selectedColor ] );

const handleIndex = async ( reset = false ) => {
setIndexing( true );
try {
let remaining = 1;
while ( remaining > 0 ) {
const response = await apiFetch( {
path: uccAdmin.root + '/index',
method: 'POST',
data: {
batch: 50,
reset: reset && remaining === 1,
scan_titles: config.scan_titles,
scan_excerpts: config.scan_excerpts,
postmeta_mode: config.postmeta_mode,
},
} );
remaining = response.remaining || 0;
if ( remaining === 0 ) {
break;
}
}
} catch ( error ) {
window.console.error( error );
} finally {
setIndexing( false );
refreshStatus();
apiFetch( { path: uccAdmin.root + '/colors' } ).then( setColors );
}
};

const handleConfigChange = ( updates ) => {
setConfig( ( current ) => ( { ...current, ...updates } ) );
};

const handlePostFetch = ( postId ) => {
if ( ! postId ) {
return;
}
apiFetch( { path: uccAdmin.root + '/by-post?post_id=' + encodeURIComponent( postId ) } ).then( setPostColors );
};

const handleDryRun = () => {
setProgress( null );
apiFetch( {
path: uccAdmin.root + '/dry-run',
method: 'POST',
data: { source, target },
} ).then( setDryRun ).catch( ( error ) => window.alert( error.message || uccAdmin.i18n.dryRunFailed ) );
};

const handleApply = async () => {
if ( ! dryRun || ! dryRun.fields || ! dryRun.fields.length ) {
window.alert( uccAdmin.i18n.noMatches );
return;
}
let remaining = dryRun.fields.length;
if ( remaining <= 0 ) {
window.alert( uccAdmin.i18n.noMatches );
return;
}
let processed = 0;
let changesetId = 0;
try {
while ( remaining > 0 ) {
const response = await apiFetch( {
path: uccAdmin.root + '/apply',
method: 'POST',
data: { source, target, changeset_id: changesetId, batch: 25 },
} );
changesetId = response.changeset_id;
processed += response.processed;
remaining = response.remaining;
setProgress( { processed, remaining } );
}
refreshStatus();
apiFetch( { path: uccAdmin.root + '/colors' } ).then( setColors );
} catch ( error ) {
window.console.error( error );
}
};

const handleUndo = ( changesetId ) => {
apiFetch( {
path: uccAdmin.root + '/undo',
method: 'POST',
data: { changeset_id: changesetId },
} ).then( refreshStatus );
};

const handleUndoAll = () => {
apiFetch( {
path: uccAdmin.root + '/undo-all',
method: 'POST',
} ).then( refreshStatus );
};

return createElement( 'div', null,
createElement( 'nav', { className: 'nav-tab-wrapper' },
createElement( 'button', { className: tab === 'dashboard' ? 'nav-tab nav-tab-active' : 'nav-tab', onClick: () => setTab( 'dashboard' ) }, uccAdmin.i18n.dashboard ),
createElement( 'button', { className: tab === 'colors' ? 'nav-tab nav-tab-active' : 'nav-tab', onClick: () => setTab( 'colors' ) }, uccAdmin.i18n.byColor ),
createElement( 'button', { className: tab === 'posts' ? 'nav-tab nav-tab-active' : 'nav-tab', onClick: () => setTab( 'posts' ) }, uccAdmin.i18n.byPost ),
createElement( 'button', { className: tab === 'history' ? 'nav-tab nav-tab-active' : 'nav-tab', onClick: () => setTab( 'history' ) }, uccAdmin.i18n.history )
),
'dashboard' === tab ? createElement( Dashboard, { status, onIndex: handleIndex, indexing, config, onConfigChange: handleConfigChange } ) : null,
'colors' === tab ? createElement( ColorsView, { colors, onSelect: setSelectedColor, selectedColor, items: colorItems } ) : null,
'posts' === tab ? createElement( PostView, { onFetch: handlePostFetch, results: postColors } ) : null,
createElement( ReplacementView, { onDryRun: handleDryRun, onApply: handleApply, dryRun, progress, source, target, setSource, setTarget } ),
'history' === tab ? createElement( HistoryView, { history: status.history || [], onUndo: handleUndo, onUndoAll: handleUndoAll } ) : null
);
}

document.addEventListener( 'DOMContentLoaded', () => {
const container = document.querySelector( '#ucc-admin-app .ucc-app' );
if ( container ) {
render( createElement( App ), container );
}
} );
})();
