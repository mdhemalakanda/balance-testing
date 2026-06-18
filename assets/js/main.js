(function($) {

    /**
     * Line chart.
     */
    $('.progress-chart').each(function() {
        var progressValues = $(this).data('progress-values');
        var data = {
            labels: ['Alkutilanne', 'R1', 'R2', 'R3'],
            series: [
                progressValues,
            ]
        };

        var options = {
            seriesBarDistance: 10,
            low: 0,
            high: 10,
            axisY: {
                onlyInteger: true,
                scaleMinSpace: 20,
            },
        };

        var responsiveOptions = [
            ['screen and (max-width: 640px)', {
                seriesBarDistance: 5,
                axisX: {
                    labelInterpolationFnc: function (value) {
                        return value[0];
                    }
                }
            }]
        ];

        new Chartist.Bar(this, data, options, responsiveOptions);
    });



    // range slider.
    $('.balance-scale').jRange({
        from: 0,
        to: 10,
        step: 1,
        scale: [1.2,3,4,5,6,7,8,9,10],
        format: '%s',
        width: 200,
        showLabels: true,
        snap: true,
        ondragend: (value) => {
            $('input[name="asteikolla"]').val(value); 
        },
        onbarclicked: (value) => {
            $('input[name="asteikolla"]').val(value); 
        }
    });
    $('.balance-scale-2').jRange({
        from: 0,
        to: 10,
        step: 1,
        scale: [1.2,3,4,5,6,7,8,9,10],
        format: '%s',
        width: 200,
        showLabels: true,
        snap: true,
        ondragend: (value) => {
            $('input[name="huimausoire"]').val(value); 
        },
        onbarclicked: (value) => {
            $('input[name="huimausoire"]').val(value); 
        }
    });

    // check form validation ( Round 01 ).
    const formData = {};
    $('.customer_question_form').on('submit', function(e) {
        let hasWarning = false;
        formData.etunimi = $('.customer_question_form').find('input[name="etunimi"]').val();
        formData.ika = $('.customer_question_form').find('input[name="ika"]').val();
        formData.tavallisimmin = $('.customer_question_form').find('input[name="tavallisimmin"]:checked').val();
        formData.user_symptom = $('.customer_question_form').find('input[name="user_symptom"]:checked').val();
        formData.dizziness_symptom = $('.customer_question_form').find('input[name="dizziness_symptom"]:checked').val();
        formData.user_activity = $('.customer_question_form').find('input[name="user_activity"]:checked').val();
        formData.user_second_activity = $('.customer_question_form').find('input[name="user_second_activity"]:checked').val();
        formData.oireiden_voimakkuus = $('.customer_question_form').find('input[name="oireiden_voimakkuus"]:checked').val();
        formData.vaikutus_toimintakykyyn = $('.customer_question_form').find('input[name="vaikutus_toimintakykyyn"]:checked').val();
        
        // Prepare message for form validation.
        let message = '<div class="bt-form-validation-box">';
        if( formData.etunimi.length === 0 ) {
            hasWarning = true;
            message += '<h5>Etunimi puuttuu</h5>';
        }

        if( formData.ika.length === 0 ) {
            hasWarning = true;
            message+= '<h5>Ikä puuttuu</h5>';
        }

        if (!formData.tavallisimmin) {
            hasWarning = true;
            message += '<h5>Valitse, kuinka usein kärsit</h5>';
        }

        if (!formData.user_symptom) {
            hasWarning = true;
            message += '<h5>Valitse, kuinka pitkään oireita on ollut</h5>';
        }

        if (!formData.dizziness_symptom) {
            hasWarning = true;
            message += '<h5>Valitse oireiden esiintymistapa</h5>';
        }

        if (!formData.oireiden_voimakkuus && formData.oireiden_voimakkuus !== '0') {
            hasWarning = true;
            message += '<h5>Arvioi oireiden voimakkuus asteikolla 0–10</h5>';
        }

        if (!formData.vaikutus_toimintakykyyn && formData.vaikutus_toimintakykyyn !== '0') {
            hasWarning = true;
            message += '<h5>Arvioi vaikutus toimintakykyyn asteikolla 0–10</h5>';
        }


        if (!formData.user_activity) {
            hasWarning = true;
            message += '<h5>Valitse Lihaskunto-kenttä</h5>';
        }

        if (!formData.user_second_activity) {
            hasWarning = true;
            message += '<h5>Valitse Huoli ja pelko -kenttä</h5>';
        }

        message += '</div>';
        if( hasWarning ) {
            e.preventDefault();
            $('.bt-form-validation-box').remove(); 
            $('.customer_question_form').prepend(message);
            $('.bt-form-validation-box').addClass('active');
        } else {
            $('.bt-form-validation-box').remove(); 
        }
    });

    // check form validation ( Round 02 Or Round 03 ).
    const formData2 = {};
    $('.customer_question_form_2').on('submit', function(e) {
        let hasWarning = false;
        formData2.exercise_days = $('.customer_question_form_2').find('input[name="exercise_days"]:checked').val();
        formData2.exercise_frequency = $('.customer_question_form_2').find('input[name="exercise_frequency"]:checked').val();
        formData2.oireiden_voimakkuus = $('.customer_question_form_2').find('input[name="oireiden_voimakkuus"]:checked').val();
        formData2.vaikutus_toimintakykyyn = $('.customer_question_form_2').find('input[name="vaikutus_toimintakykyyn"]:checked').val();
        // Prepare message for form validation.
        let message = '<div class="bt-form-validation-box">';

        if (!formData2.oireiden_voimakkuus && formData2.oireiden_voimakkuus !== '0') {
            hasWarning = true;
            message += '<h5>Arvioi oireiden voimakkuus asteikolla 0–10</h5>';
        }

        if (!formData2.vaikutus_toimintakykyyn && formData2.vaikutus_toimintakykyyn !== '0') {
            hasWarning = true;
            message += '<h5>Arvioi vaikutus toimintakykyyn asteikolla 0–10</h5>';
        }
        
        if (!formData2.exercise_days) {
            hasWarning = true;
            message += '<h5>Kuinka monena päivänä teit suositeltuja harjoituksia?</h5>';
        }
        if (!formData2.exercise_frequency) {
            hasWarning = true;
            message += '<h5>Niinä päivinä, kun teit harjoituksia, kuinka monta kertaa päivässä keskimäärin teit ne?</h5>';
        }

        message += '</div>';
        if( hasWarning ) {
            e.preventDefault();
            $('.bt-form-validation-box').remove(); 
            $('.customer_question_form_2').prepend(message);
            $('.bt-form-validation-box').addClass('active');
        } else {
            $('.bt-form-validation-box').remove(); 
        }
    });

    /**
     * Rating plugin custom.
     */
    $.fn.simpleRating = function(options) {
        var settings = $.extend({
            max: 6,
            onRate: function(rating) {} // callback when a rating is selected
        }, options);

        return this.each(function() {
            var $container = $(this);
            $container.empty(); // clear existing content

            // Get initial active rating from attribute
            var activeRating = parseInt($container.attr('active')) || 0;

            for(var i=1; i<=settings.max; i++) {
                var $btn = $('<button type="button" class="rate-btn">'+i+'</button>');
                $btn.data('rating', i);
                // Set initial active class
                if(i === activeRating) {
                    $btn.addClass('selected');
                }
                $container.append($btn);
            }

            $container.on('click', '.rate-btn', function(){
                var rating = $(this).data('rating');
                $container.find('.rate-btn').removeClass('selected');
                $(this).addClass('selected');
                $container.closest('.bt-user-rating-box').find('input[name="user_balance_test_rating"]').val(rating);
                settings.onRate(rating);
            });
        });
    };

    $('#test-rating').simpleRating({
        max: 6,
        onRate: function(rating) {
            $('#rating-message').text('Arvioit testin asteikolla 1-6: ' + rating);
        }
    });




})(jQuery)