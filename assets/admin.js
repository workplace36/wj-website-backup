/* global WJBA43_DATA, Razorpay */
(function(){
  var razorpayLoadPromise = null;

function loadRazorpayCheckout() {
    if (window.Razorpay) {
        return Promise.resolve();
    }

    if (razorpayLoadPromise) {
        return razorpayLoadPromise;
    }

    razorpayLoadPromise = new Promise(function(resolve, reject) {
        var script = document.createElement('script');

        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.async = true;

        script.onload = function() {
            if (window.Razorpay) {
                resolve();
            } else {
                razorpayLoadPromise = null;
                reject(new Error('Razorpay Checkout failed to initialize.'));
            }
        };

        script.onerror = function() {
            razorpayLoadPromise = null;
            reject(new Error('Unable to load Razorpay Checkout. Please try again.'));
        };

        document.head.appendChild(script);
    });

    return razorpayLoadPromise;
}
  var sizeBtn=document.getElementById('wjba-check-size'),result=document.getElementById('wjba-size-result'),loading=document.getElementById('wjba-size-loading'),nonce=WJBA43_DATA.nonces.siteSize,ajaxEndpoint=WJBA43_DATA.ajaxUrl;
  function updatePlans(gb){document.querySelectorAll('.wjba-plan-card').forEach(function(card){var max=parseFloat(card.dataset.max||0),btn=card.querySelector('.wjba-subscribe'),note=card.querySelector('.wjba-plan-note');if(!btn)return;var configured=btn.dataset.configured==='1';if(gb<=max){if(configured){btn.disabled=false;btn.textContent='Subscribe Now';note.textContent='Your website fits this plan.';}else{btn.disabled=true;btn.textContent='Razorpay Setup Pending';note.textContent='This plan is not yet enabled for online payment.';}}else{btn.disabled=true;btn.textContent='Not Eligible';note.textContent='Website exceeds this plan\'s '+max+' GB limit.';}});}
 

function renderPlanCards(plans) {
  var grid = document.getElementById('wjba-plan-grid');
  if (!grid) return;

  grid.replaceChildren();

  var currentPlan = String(WJBA43_DATA.currentPlan || '').toLowerCase();
  var subscriptionActive = WJBA43_DATA.subscriptionActive === true;

  var current = plans.find(function(p) {
    return String(p.name || '').toLowerCase() === currentPlan;
  });
  var currentPrice = current ? Number(current.price) : 0;

  plans.forEach(function(p) {
    
var card = document.createElement('div');
card.className = 'wjba-plan-card';
card.dataset.max = String(p.max_website_gb || 0);

card.style.cssText =
  'background:#fff;' +
  'border:1px solid #dcdcde;' +
  'border-radius:8px;' +
  'padding:20px;' +
  'box-sizing:border-box;' +
  'position:relative;' +
  'min-width:0;';


    var name = String(p.name || 'Backup Plan');
    var price = Number(p.price || 0);
    var currency = String(p.currency || '').toUpperCase();

    var symbols = {
      INR: '₹',
      USD: '$',
    //  GBP: '£',
   //   EUR: '€',
   //   CAD: 'CA$',
    //  AUD: 'A$'
    };
    var symbol = symbols[currency] || (currency ? currency + ' ' : '');

    var heading = document.createElement('h3');
    heading.textContent = name;
    card.appendChild(heading);

    var priceLine = document.createElement('div');
    priceLine.style.cssText = 'font-size:25px;font-weight:700';
    priceLine.textContent = symbol + price.toLocaleString(undefined, {
      maximumFractionDigits: 2
    });

    var monthly = document.createElement('small');
    monthly.style.cssText = 'font-size:13px;font-weight:400';
    monthly.textContent = ' / month';
    priceLine.appendChild(monthly);
    card.appendChild(priceLine);

    var details = document.createElement('p');
    details.textContent =
      'Max website: ' + Number(p.max_website_gb || 0) + ' GB';

    details.appendChild(document.createElement('br'));
    details.appendChild(document.createTextNode(
      'Backup storage: ' + Number(p.storage_limit_gb || 0) + ' GB'
    ));

    details.appendChild(document.createElement('br'));
    details.appendChild(document.createTextNode(
      'Retention: ' + Number(p.retention_days || 0) + ' days'
    ));

    details.appendChild(document.createElement('br'));
    details.appendChild(document.createTextNode('Daily database + files backup'));
    card.appendChild(details);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-primary';

    var isCurrent = currentPlan === name.toLowerCase();

    if (isCurrent) {
      button.className = 'button';
      button.textContent = 'Current Plan';
      button.disabled = true;
    } else if (subscriptionActive && price > currentPrice) {
      button.classList.add('wjba-upgrade');
      button.dataset.plan = String(p.id);
      button.dataset.planName = name;
      button.textContent = 'Upgrade to ' + name;
    } else if (subscriptionActive && price < currentPrice) {
      button.textContent = 'Lower Plan';
      button.disabled = true;
    } else {
      button.classList.add('wjba-subscribe');
      button.dataset.plan = String(p.id);
      button.dataset.configured = p.configured ? '1' : '0';
      button.disabled = true;
      button.textContent = 'Check Website Size First';
    }

    card.appendChild(button);

    var note = document.createElement('div');
    note.className = 'wjba-plan-note';
    note.style.cssText = 'margin-top:8px;font-size:12px;color:#666';
    card.appendChild(note);

    grid.appendChild(card);
  });
}

  function loadPlans(gb) {
    var grid = document.getElementById('wjba-plan-grid');
    var message = document.getElementById('wjba-plans-message');

    if (!grid || !message) return;


var consent = document.getElementById('wjba-ip-consent');

if (!consent || !consent.checked) {
    grid.style.display = 'none';
    message.style.display = 'block';
    message.textContent =
        'Please agree to share your public IP address to determine your country and applicable subscription pricing.';
    return;
}


    message.textContent = 'Loading available plans…';

    var f = new FormData();
    f.append('action', 'wjba43_load_plans');
    f.append('_ajax_nonce', WJBA43_DATA.nonces.loadPlans);
    f.append('ip_consent', 'yes');

    fetch(WJBA43_DATA.ajaxUrl, {
      method: 'POST',
      body: f,
      credentials: 'same-origin'
    })
    .then(function(r) {
      return r.json();
    })
    .then(function(x) {
      if (!x || !x.success) {
        throw new Error(
          x && x.data && x.data.message
            ? x.data.message
            : 'Unable to load plans. Please try again.'
        );
      }

      var data = x.data || {};

      if (data.blocked) {
        grid.style.display = 'none';
        message.textContent = 'Subscriptions are not available in your country.';
        return;
      }

      if (!Array.isArray(data.plans) || !data.plans.length) {
        grid.style.display = 'none';
        message.textContent = data.message || 'No plans are currently available.';
        return;
      }

      /*
       * Do not inject server-returned HTML.
       * Plan-card rendering will be added in the next step.
       */
      grid.style.display = 'grid';
      message.textContent =
        'Plans received. Preparing plan cards…';

      grid.dataset.countryCode = data.country_code || '';

renderPlanCards(data.plans);
updatePlans(gb);

grid.style.display = 'grid';
grid.style.gridTemplateColumns =
  'repeat(auto-fit,minmax(220px,1fr))';
grid.style.gap = '16px';
grid.style.maxWidth = '1100px';
message.style.display = 'none';
    })
    .catch(function(e) {
      grid.style.display = 'none';
      message.textContent =
        e.message || 'Unable to load plans. Please try again.';
    });
  }

  if(sizeBtn){
    sizeBtn.addEventListener('click',function(){
      sizeBtn.disabled=true;
      loading.textContent='Calculating…';
      result.innerHTML='<span>Calculating website size… Please wait.</span>';
      var f=new FormData();
      f.append('action','wjba43_site_size');
      f.append('_ajax_nonce',nonce);
      fetch(ajaxEndpoint,{method:'POST',body:f,credentials:'same-origin'})
        .then(function(r){return r.text().then(function(t){var x;try{x=JSON.parse(t);}catch(e){throw new Error('The server returned an invalid response. Please check the WordPress error log.');}return {ok:r.ok,data:x};});})
        .then(function(res){
          var x=res.data;
          if(!x||!x.success) throw new Error(x&&x.data&&x.data.message?x.data.message:'Size check failed.');
          var d=x.data||{},gb=(Number(d.total_bytes)||0)/1073741824;
          result.innerHTML='<strong>Website size:</strong> '+(d.total||'0 B')+'<br><strong>Files:</strong> '+((Number(d.files_bytes)||0)/1073741824).toFixed(2)+' GB<br><strong>Database:</strong> '+((Number(d.database_bytes)||0)/1048576).toFixed(2)+' MB';
          if (gb > 50) {
  result.innerHTML += '<br><span style="color:#b32d2e;font-weight:700">This website is over the 50 GB maximum supported size. Backup is not available.</span>';

  var grid = document.getElementById('wjba-plan-grid');
  if (grid) {
    grid.replaceChildren();
    grid.style.display = 'none';
  }

  var plansMessage = document.getElementById('wjba-plans-message');
  if (plansMessage) {
    plansMessage.textContent = 'This website exceeds the 50 GB maximum supported size.';
    plansMessage.style.display = 'block';
  }
} else {
  result.innerHTML += '<br><span style="color:#008a20;font-weight:700">Website size checked. Loading available plans…</span>';
  loadPlans(gb);
}
        })
        .catch(function(e){result.innerHTML='<span style="color:#b32d2e;font-weight:600">'+String(e.message||e).replace(/[<>]/g,'')+'</span>';})
        .finally(function(){sizeBtn.disabled=false;loading.textContent='';});
    });
  }
var disconnectForm = document.getElementById('wjba-disconnect-form');

if (disconnectForm) {
  disconnectForm.addEventListener('submit', function(event) {
    if (!window.confirm('Are you sure you want to disconnect your website backup?')) {
      event.preventDefault();
    }
  });
}
  var reconnect=document.getElementById('wjba-reconnect');if(reconnect){reconnect.addEventListener('click',function(){reconnect.disabled=true;reconnect.textContent='Reconnecting…';var f=new FormData();f.append('action','wjba43_reconnect');f.append('_ajax_nonce',WJBA43_DATA.nonces.reconnect);fetch(WJBA43_DATA.ajaxUrl,{method:'POST',body:f}).then(function(r){return r.json()}).then(function(x){if(x.success){location.reload();}else{throw new Error(x.data&&x.data.message?x.data.message:'Reconnect failed.');}}).catch(function(e){alert(e.message);reconnect.disabled=false;reconnect.textContent='Reconnect Subscription';});});}

  document.addEventListener('click', function(event) {
    var btn = event.target.closest('.wjba-upgrade');
    if (!btn) return;
    if (btn.disabled) return;

      var plan=btn.dataset.plan;
      var planName=btn.dataset.planName||'selected plan';

      if(!confirm('Upgrade your subscription to '+planName+'? You will be redirected to Razorpay to authorize the new recurring subscription.'))return;

      btn.disabled=true;
      var original=btn.textContent;
      btn.textContent='Creating Subscription…';

      var f=new FormData();
      f.append('action','wjba43_upgrade_subscription');
      f.append('plan_id',plan);
      f.append('_ajax_nonce',WJBA43_DATA.nonces.subscription);

      fetch(WJBA43_DATA.ajaxUrl,{method:'POST',body:f})
        .then(function(r){
          return r.json().then(function(x){return {http:r.status,data:x};});
        })
        .then(function(result){
          var x=result.data;

          if(!x.success){
            throw new Error(x.data&&x.data.message?x.data.message:'Could not create upgrade subscription.');
          }

          var d=x.data||{};
          var subscriptionId=d.subscription_id||d.razorpay_subscription_id||'';
          var keyId=d.razorpay_key_id||'';

          if(!subscriptionId||!keyId){
            throw new Error('Razorpay Subscription details were not returned.');
          }



          var options={
            key:keyId,
            subscription_id:subscriptionId,
            name:'Webjooll',
            description:planName+' Website Backup Upgrade',
            prefill:{
              email:WJBA43_DATA.email,
              name:WJBA43_DATA.name
            },
            handler:function(response){
              btn.textContent='Verifying Subscription…';

              var returnedSubscriptionId=response.razorpay_subscription_id||subscriptionId;
              var paymentId=response.razorpay_payment_id||'';
              var signature=response.razorpay_signature||'';

              if(!returnedSubscriptionId||!paymentId||!signature){
                alert('Razorpay did not return complete subscription verification data.');
                btn.disabled=false;
                btn.textContent=original;
                return;
              }

              var attempts=0;
              var maxAttempts=10;

              function verifyUpgrade(){
                attempts++;
                var f2=new FormData();
                f2.append('action','wjba43_verify_upgrade_payment');
                f2.append('razorpay_subscription_id',returnedSubscriptionId);
                f2.append('razorpay_payment_id',paymentId);
                f2.append('razorpay_signature',signature);
                f2.append('_ajax_nonce',WJBA43_DATA.nonces.subscription);

                fetch(WJBA43_DATA.ajaxUrl,{method:'POST',body:f2})
                  .then(function(r){
                    return r.json().then(function(v){return {http:r.status,data:v};});
                  })
                  .then(function(v){
                    if(v.data&&v.data.success){
                      location.reload();
                      return;
                    }

                    var msg=v.data&&v.data.data&&v.data.data.message
                      ?v.data.data.message
                      :'Upgrade verification is still pending.';
                    var lower=String(msg).toLowerCase();
                    var retryable=(v.http===409)||
                      lower.indexOf('not authorized yet')!==-1||
                      lower.indexOf('pending')!==-1||
                      lower.indexOf('created')!==-1||
                      lower.indexOf('processing')!==-1;

                    if(retryable&&attempts<maxAttempts){
                      setTimeout(verifyUpgrade,3000);
                      return;
                    }

                    throw new Error(msg);
                  })
                  .catch(function(e){
                    var lower=String(e.message||'').toLowerCase();
                    if(attempts<maxAttempts&&lower.indexOf('network')!==-1){
                      setTimeout(verifyUpgrade,3000);
                      return;
                    }
                    alert(e.message||'Upgrade verification failed.');
                    btn.disabled=false;
                    btn.textContent=original;
                  });
              }

              verifyUpgrade();
            },
            modal:{
              ondismiss:function(){
                btn.disabled=false;
                btn.textContent=original;
              }
            },
            theme:{color:'#3399cc'}
          };

                return loadRazorpayCheckout().then(function() {
            var rzp = new Razorpay(options);

            rzp.on('payment.failed', function(response) {
              btn.disabled = false;
              btn.textContent = original;

              var message = 'Subscription authorization failed.';
              if (response.error && response.error.description) {
                message += ' ' + response.error.description;
              }

              alert(message);
            });

            rzp.open();
          });


        })
        .catch(function(e){
          alert(e.message);
          btn.disabled=false;
          btn.textContent=original;
        });
    });

  document.addEventListener('click', function(event) {
    var btn = event.target.closest('.wjba-subscribe');
    if (!btn) return;
    if (btn.disabled) return;

      var plan=btn.dataset.plan;
      btn.disabled=true;

      var original=btn.textContent;
      btn.textContent='Creating Subscription…';

      /*
       * IMPORTANT:
       * This is the recurring Razorpay Subscription flow.
       * Do NOT call create_checkout_order / verify_checkout_payment here.
       */
      var f=new FormData();
      f.append('action','wjba43_create_subscription');
      f.append('plan_id',plan);
      f.append('_ajax_nonce',WJBA43_DATA.nonces.subscription);

      fetch(WJBA43_DATA.ajaxUrl,{method:'POST',body:f})
        .then(function(r){
          return r.json().then(function(x){
            return {http:r.status,data:x};
          });
        })
        .then(function(result){
          var x=result.data;

          if(!x.success){
            throw new Error(
              x.data&&x.data.message
                ? x.data.message
                : 'Could not create Razorpay subscription.'
            );
          }

          var d=x.data||{};

          /* Server found an existing active subscription and reconnected it. */
          if(d.reconnected){
            location.reload();
            return;
          }

          var sub=d.subscription||{};
          var subscriptionId=sub.id||'';
          var keyId=d.razorpay_key_id||'';

          if(!subscriptionId){
            throw new Error('Razorpay subscription ID was not returned.');
          }

          if(!keyId){
            throw new Error('Razorpay public key was not returned.');
          }



          btn.textContent='Opening Secure Checkout…';

          var options={
            key:keyId,
            subscription_id:subscriptionId,
            name:'Webjooll',
            description:(d.plan&&d.plan.name?d.plan.name:'Website Backup')+' Website Backup',
            prefill:{
              email:WJBA43_DATA.email,
              name:WJBA43_DATA.name
            },

            handler:function(response){

              /*
               * Razorpay has returned from Subscription Checkout.
               * The authoritative server-side claim verifies the
               * Razorpay subscription itself before issuing the API token.
               */
              btn.textContent='Activating Subscription…';

              var attempts=0;
              var maxAttempts=8;

              function claimSubscription(){

                attempts++;

                var cf=new FormData();
                cf.append('action','wjba43_claim_subscription');
                cf.append('_ajax_nonce',WJBA43_DATA.nonces.subscription);

                fetch(WJBA43_DATA.ajaxUrl,{method:'POST',body:cf})
                  .then(function(r){
                    return r.json().then(function(x){
                      return {http:r.status,data:x};
                    });
                  })
                  .then(function(result2){

                    var x2=result2.data;

                    if(x2.success){
                      location.reload();
                      return;
                    }

                    var message=
                      x2.data&&x2.data.message
                        ? x2.data.message
                        : 'Subscription activation is still pending.';

                    /*
                     * Razorpay may need a few seconds to move the
                     * subscription to ACTIVE. Retry only for the
                     * pending activation state.
                     */
                    var retryable =
                      message.indexOf('still being activated') !== -1 ||
                      message.indexOf('not active yet') !== -1 ||
                      result2.http===409;

                    if(retryable && attempts<maxAttempts){
                      btn.textContent='Waiting for Subscription Activation… ('+attempts+'/'+maxAttempts+')';
                      setTimeout(claimSubscription,2000);
                      return;
                    }

                    throw new Error(message);
                  })
                  .catch(function(e){

                    if(attempts<maxAttempts && /still being activated|not active yet/i.test(e.message||'')){
                      setTimeout(claimSubscription,2000);
                      return;
                    }

                    alert(
                      'Payment was received, but subscription activation failed: '+
                      e.message+
                      '\n\nPlease do NOT pay again. The Razorpay subscription/payment has already been created.'
                    );

                    btn.disabled=false;
                    btn.textContent=original;
                  });
              }

              claimSubscription();
            },

            modal:{
              ondismiss:function(){
                btn.disabled=false;
                btn.textContent=original;
              }
            },

            theme:{color:'#3399cc'}
          };

                    return loadRazorpayCheckout().then(function() {
            var rzp = new Razorpay(options);

            rzp.on('payment.failed', function(response) {
              btn.disabled = false;
              btn.textContent = original;

              var message = 'Payment failed.';
              if (response.error && response.error.description) {
                message += ' ' + response.error.description;
              }

              alert(message);
            });

            rzp.open();
          });
        })
        .catch(function(e){
          alert(e.message);
          btn.disabled=false;
          btn.textContent=original;
        });
  });
 })();
