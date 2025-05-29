(function (wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginBlockSettingsMenuItem } = wp.editPost; // For block specific menu
    const { select, dispatch, subscribe } = wp.data;
    const { createElement, Fragment, useState, useEffect } = wp.element;
    const { Button, Modal, TextareaControl, PanelBody, Spinner } = wp.components; // Added Spinner
    const { __ } = wp.i18n;
    const { parse } = wp.blocks; // To parse HTML string to blocks

    const GEMINI_DUPLICATOR_BLOCK_REWRITE_ICON = 'text-page';

    const SUPPORTED_BLOCKS = [
        'core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/details',
    ];

    function GeminiBlockRewriterControl() {
        // Block specific rewrite states
        const [selectedBlockClientId, setSelectedBlockClientId] = useState(null);
        const [selectedBlockType, setSelectedBlockType] = useState(null);
        const [isRewriteModalOpen, setIsRewriteModalOpen] = useState(false);
        const [customRewritePromptText, setCustomRewritePromptText] = useState('');
        const [blockContentSnippet, setBlockContentSnippet] = useState('');

        // Initial content generation states
        const [isInitialContentModalOpen, setIsInitialContentModalOpen] = useState(false);
        const [initialContentPrompt, setInitialContentPrompt] = useState('');
        const [isGeneratingInitialContent, setIsGeneratingInitialContent] = useState(false);
        
        // Effect for handling initial content modal based on localized params
        useEffect(() => {
            if (gpd_editor_params && gpd_editor_params.showAiModalOnLoad && gpd_editor_params.initialPrompt) {
                setInitialContentPrompt(gpd_editor_params.initialPrompt);
                setIsInitialContentModalOpen(true);
                // Clear the flag so it doesn't re-show on other editor loads for this session (PHP transient will prevent future loads)
                // This is a client-side clear for the current view.
                // A better way might be to clear the transient via an AJAX call upon modal open or action.
                gpd_editor_params.showAiModalOnLoad = false; 
            }
        }, []); // Empty dependency array means this runs once on component mount

        // Effect for subscribing to block selection changes
        useEffect(() => {
            const unsubscribe = subscribe(() => {
                const currentBlockId = select('core/block-editor').getSelectedBlockClientId();
                if (currentBlockId && currentBlockId !== selectedBlockClientId) {
                    const block = select('core/block-editor').getBlock(currentBlockId);
                    if (block && SUPPORTED_BLOCKS.includes(block.name)) {
                        setSelectedBlockClientId(currentBlockId);
                        setSelectedBlockType(block.name);
                        let snippet = '';
                        if (block.attributes.content) { snippet = block.attributes.content; }
                        else if (block.attributes.values) { snippet = block.attributes.values; }
                        else if (block.attributes.value) { snippet = block.attributes.value; }
                        else if (block.attributes.summary) { snippet = block.attributes.summary; }
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = snippet;
                        snippet = (tempDiv.textContent || tempDiv.innerText || "").trim();
                        setBlockContentSnippet(snippet.substring(0, 200) + (snippet.length > 200 ? '...' : ''));
                    } else if (selectedBlockClientId) {
                        setSelectedBlockClientId(null); setSelectedBlockType(null); setBlockContentSnippet('');
                    }
                } else if (!currentBlockId && selectedBlockClientId) {
                    setSelectedBlockClientId(null); setSelectedBlockType(null); setBlockContentSnippet('');
                }
            });
            return () => unsubscribe();
        }, [selectedBlockClientId]);

        const openRewriteModal = () => {
            if (!selectedBlockClientId) return;
            setCustomRewritePromptText('');
            setIsRewriteModalOpen(true);
        };
        const closeRewriteModal = () => setIsRewriteModalOpen(false);

        const handleSubmitBlockRewrite = () => {
            closeRewriteModal();
            // ... (AJAX call for block rewrite as previously defined)
            const block = select('core/block-editor').getBlock(selectedBlockClientId);
            if(!block) return;
            wp.ajax.post('gpd_rewrite_block_content', {
                nonce: gpd_editor_params.nonce, // Block rewrite nonce
                clientId: selectedBlockClientId,
                blockName: block.name,
                attributes: block.attributes,
                customPrompt: customRewritePromptText,
            }).done(function(response) { /* ... existing done ... */ 
                if (response.success && response.data && response.data.newAttributes) {
                    dispatch('core/block-editor').updateBlockAttributes(selectedBlockClientId, response.data.newAttributes);
                    dispatch('core/notices').createSuccessNotice(__('Block content rewritten by Gemini!', 'gemini-page-duplicator'), { type: 'snackbar' });
                } else {
                    let msg = (response.data && response.data.message) ? response.data.message : __('Unknown error during block rewrite.', 'gemini-page-duplicator');
                    dispatch('core/notices').createErrorNotice(msg, { type: 'snackbar', isDismissible: true });
                }
            }).fail(function(jqXHR) { /* ... existing fail ... */ 
                let msg = __('AJAX request failed for block rewrite.', 'gemini-page-duplicator');
                try { const err = JSON.parse(jqXHR.responseText); if (err && err.data && err.data.message) msg = err.data.message; } catch (e) {}
                dispatch('core/notices').createErrorNotice(msg, { type: 'snackbar', isDismissible: true });
            });
        };

        const closeInitialContentModal = () => setIsInitialContentModalOpen(false);

        const handleGenerateInitialContent = () => {
            setIsGeneratingInitialContent(true);
            wp.ajax.post('gpd_generate_initial_content', {
                nonce: gpd_editor_params.generateNonce, // Separate nonce for this action
                post_id: gpd_editor_params.currentPostId,
                initial_prompt: initialContentPrompt,
            }).done(function(response) {
                setIsGeneratingInitialContent(false);
                if (response.success && response.data && response.data.generated_content_html) {
                    const rawHtml = response.data.generated_content_html;
                    const newBlocks = parse(rawHtml);

                    if (newBlocks.length === 0 && rawHtml.trim() !== '') {
                        // Parsing failed or resulted in no blocks from non-empty content
                        console.error('Gemini Duplicator: Failed to parse HTML from Gemini into blocks. HTML:', rawHtml);
                        dispatch('core/notices').createErrorNotice(
                            gpd_editor_params.strings.contentGeneratedError + ' ' + __('(Could not parse generated HTML into blocks). Please try again or manually edit.', 'gemini-page-duplicator'),
                            { type: 'snackbar', isDismissible: true }
                        );
                         // Insert raw HTML into a classic block or a single paragraph as a fallback? Or leave editor empty?
                        // For now, leave as is, user can copy/paste or try again.
                        // Alternatively, to ensure something is inserted:
                        // const fallbackBlock = wp.blocks.createBlock('core/freeform', { content: rawHtml });
                        // dispatch('core/block-editor').resetBlocks([fallbackBlock]);

                    } else {
                        dispatch('core/block-editor').resetBlocks(newBlocks.length ? newBlocks : [wp.blocks.createBlock('core/paragraph', {})] );
                        dispatch('core/notices').createSuccessNotice(
                            gpd_editor_params.strings.contentGeneratedSuccess || __('Content generated and inserted!', 'gemini-page-duplicator'),
                            { type: 'snackbar' }
                        );
                        closeInitialContentModal();
                        // dispatch('core/editor').editPost({}); // Mark post as dirty
                    }
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : (gpd_editor_params.strings.contentGeneratedError || __('Error generating content.', 'gemini-page-duplicator'));
                    dispatch('core/notices').createErrorNotice(errorMessage, { type: 'snackbar', isDismissible: true });
                }
            }).fail(function(jqXHR) {
                setIsGeneratingInitialContent(false);
                console.error('AJAX Error for initial content:', jqXHR.statusText, jqXHR.responseText);
                let errorMessage = __('AJAX request failed for initial content generation.', 'gemini-page-duplicator');
                try { const err = JSON.parse(jqXHR.responseText); if (err && err.data && err.data.message) errorMessage = err.data.message; } catch (e) {}
                dispatch('core/notices').createErrorNotice(errorMessage, { type: 'snackbar', isDismissible: true });
            });
        };
        
        // Render block rewriter UI (menu item and its modal)
        const renderBlockRewriter = () => {
            if (!selectedBlockClientId || !SUPPORTED_BLOCKS.includes(selectedBlockType)) {
                return null; 
            }
            return createElement(
                Fragment, null,
                createElement(PluginBlockSettingsMenuItem, {
                    allowedBlocks: SUPPORTED_BLOCKS,
                    icon: GEMINI_DUPLICATOR_BLOCK_REWRITE_ICON,
                    label: __('Rewrite with Gemini', 'gemini-page-duplicator'),
                    onClick: openRewriteModal,
                }),
                isRewriteModalOpen && createElement(Modal, { title: __('Rewrite Block Content with Gemini', 'gemini-page-duplicator'), onRequestClose: closeRewriteModal, className: 'gpd-rewrite-modal' },
                    createElement(PanelBody, null,
                        createElement('p', null, createElement('strong', null, __('Selected text snippet (for context):', 'gemini-page-duplicator'))),
                        createElement('p', { style: { fontStyle: 'italic', maxHeight: '100px', overflowY: 'auto', background: '#f0f0f0', padding: '5px', marginBottom: '1em'} }, blockContentSnippet || __('Could not extract a snippet.')),
                        createElement(TextareaControl, {
                            label: __('Your Custom Prompt/Instructions for Gemini:', 'gemini-page-duplicator'),
                            value: customRewritePromptText,
                            onChange: setCustomRewritePromptText,
                            help: __('e.g., "Make this more formal", "Translate to Spanish", "Summarize this text". If empty, a default rewrite instruction will be used.', 'gemini-page-duplicator'),
                            rows: 4, style: { width: '100%', marginBottom: '1em' }
                        })
                    ),
                    createElement('div', { className: 'gpd-modal-actions', style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', paddingTop: '1em', borderTop: '1px solid #eee' } },
                        createElement(Button, { isSecondary: true, onClick: closeRewriteModal }, __('Cancel', 'gemini-page-duplicator')),
                        createElement(Button, { isPrimary: true, onClick: handleSubmitBlockRewrite, disabled: !selectedBlockClientId }, __('Submit to Gemini', 'gemini-page-duplicator'))
                    )
                )
            );
        };

        // Render initial content modal
        const renderInitialContentModal = () => {
            if (!isInitialContentModalOpen) return null;

            return createElement(Modal, { title: gpd_editor_params.strings.modalTitle || __('Generate AI Content', 'gemini-page-duplicator'), onRequestClose: closeInitialContentModal, isDismissible: !isGeneratingInitialContent },
                createElement(PanelBody, null, 
                    createElement('p', null, createElement('strong', null, gpd_editor_params.strings.modalPromptLabel || __('Initial prompt for Gemini:','gemini-page-duplicator'))),
                    createElement('p', { style: { fontStyle: 'italic', background: '#f0f0f0', padding: '10px', borderRadius: '3px', maxHeight: '150px', overflowY: 'auto' } }, initialContentPrompt),
                    isGeneratingInitialContent && createElement(Spinner)
                ),
                !isGeneratingInitialContent && createElement('div', { className: 'gpd-modal-actions', style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', paddingTop: '1em', borderTop: '1px solid #eee' } },
                    createElement(Button, { isSecondary: true, onClick: closeInitialContentModal }, gpd_editor_params.strings.modalCancelButton || __('Manually Edit Instead', 'gemini-page-duplicator')),
                    createElement(Button, { isPrimary: true, onClick: handleGenerateInitialContent }, gpd_editor_params.strings.modalGenerateButton || __('Generate Content with Gemini', 'gemini-page-duplicator'))
                )
            );
        };

        return createElement(Fragment, null, renderBlockRewriter(), renderInitialContentModal());
    }

    registerPlugin('gemini-page-duplicator-enhancements', { // Changed plugin name for clarity
        render: GeminiBlockRewriterControl, // Main component now handles both features
        icon: null, 
    });

})(window.wp);
