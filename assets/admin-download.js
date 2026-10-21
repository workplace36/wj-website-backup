/* global WJBA43_DATA */
(function(){

        document.querySelectorAll('.wjba-download').forEach(function(btn){

            btn.addEventListener('click', function(){

                if(btn.disabled) return;

                var backupId = btn.getAttribute('data-id');

                if(!backupId){
                    alert('Invalid backup ID.');
                    return;
                }

                var originalText = btn.textContent;

                btn.disabled = true;
                btn.textContent = 'Preparing…';

                var form = new FormData();

                form.append('action', 'wjba43_download');
                form.append('backup_id', backupId);
                form.append(
                    '_ajax_nonce',
                    WJBA43_DATA.nonces.download
                );

                fetch(
                    WJBA43_DATA.ajaxUrl,
                    {
                        method: 'POST',
                        body: form
                    }
                )
                .then(function(response){
                    return response.json();
                })
                .then(function(result){

                    if(!result.success){

                        throw new Error(
                            result.data &&
                            result.data.message
                                ? result.data.message
                                : 'Download failed.'
                        );

                    }

                    if(
                        !result.data ||
                        !result.data.url
                    ){

                        throw new Error(
                            'Download URL was not returned.'
                        );

                    }

                    window.location.href = result.data.url;

                })
                .catch(function(error){

                    alert(error.message);

                })
                .finally(function(){

                    btn.disabled = false;
                    btn.textContent = originalText;

                });

            });

        });

    })();
