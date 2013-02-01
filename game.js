var _tripleClickTimer = 0;
document.ondblclick = function(evt) {
    ClearSelection();
    window.clearTimeout(_tripleClickTimer);

    //handle triple click selecting whole paragraph
    document.onclick = function() {
        ClearSelection();
    };

    _tripleClickTimer = window.setTimeout(function() {
           document.onclick = null; 
    }, 1000);
};

function ClearSelection() {
    if (window.getSelection)
        window.getSelection().removeAllRanges();
    else if (document.selection)
        document.selection.empty();
}

$(function () {
    "use strict";
    
    ////////////////////////////////////////////////////////////////////////////
    // Start defining grammar for speech balloon.
    
    var OTHER_OPTION = "[other]";
    var RANDOMIZE_OPTION = "[randomize]";
    
    var grammar = {
        utterance: [ "$assertion[Assert a fact]", "$question[Ask a question]", "$confirm[Say yes]", "$denial[Say no]", "$greet[Greet]" ],
        
        assertion: [ "Thank you.", "Please.", "That's excellent!", "I'm not sure.", "I don't understand.", "I am sorry.", "That's too bad to hear." ],
        question: [ "What do you mean?" ],
        confirm: [ "Yes.", "Yes, $reason_to_say_yes." ],
        denial: [ "No.", "No, $reason_to_say_no." ],
        greet: [ "Hello.", "Goodbye." ],
        
        reason_to_say_yes: ["that's possible", "that is okay", OTHER_OPTION],
        reason_to_say_no: ["that's not possible", "that is not okay", OTHER_OPTION],
        
        type_of_animal: ["dog", "cat", "hamster", "goldfish", "parrot", OTHER_OPTION, RANDOMIZE_OPTION],
        money: ["5", "10", "15", "20", OTHER_OPTION],
        property: ["big", "good with children", "cheap to maintain", "cheap to buy", "deterrent to burglars", "able to catch mice", "easy to transport", OTHER_OPTION, RANDOMIZE_OPTION],
    };
    
    var customer_grammar = {
        reason_to_say_yes: [ "I need to know more" ],
        reason_to_say_no: [ "I know enough" ],
        question: [ "Can you tell me if a $type_of_animal is $property?", "Do you sell $type_of_animal?", "How much does a $type_of_animal cost?", "Do you sell $type_of_animal for $money euro?", "What pets do you sell?", "I would like to buy a $type_of_animal.", "I am looking for information on $type_of_animal.", "I am looking for a pet that is $property." ],
    };
    
    var shopkeep_grammar = {
        assertion: [ "We sell $type_of_animal.", "We don't sell $type_of_animal.", "A $type_of_animal costs $money euro.", "A $type_of_animal is a pet that is $property.", "We don't have any $type_of_animal in stock." ],
        question: [ "How may I help you?", "Would you like to know more?" ],
        greet: [ "Welcome.", "At your service." ],
    };
    
    function reloadGrammar(extension_grammar) {
        var field;
        for ( field in extension_grammar ) {
            if ( field in grammar ) {
                grammar[field] = grammar[field].concat(extension_grammar[field]);
            } else {
                grammar[field] = extension_grammar[field];
            }
        }
    }
    
    // Done defining grammar for speech balloon.
    ////////////////////////////////////////////////////////////////////////////
	    
    ////////////////////////////////////////////////////////////////////////////
    // Start defining helper functions for speech balloon.
    
    var ROOT_OPTION_ID = "utterance";
    var UNDO_OPTION_ID = "undo";
    var OTHER_OPTION_ID = "other";
    
    var ROLE_SHOPKEEP = "shopkeep";
    var ROLE_CUSTOMER = "customer";
    
    var gameStarted = false;
    
    var playerRole = null;
    
    var animalPaid = false;
    var animalGiven = false;
    
    var variableRegex = /\$(\w+)(\[([\w ]+?)\])?/g;
    
    var waitingHtml = '<img src="_/img/ajax-loader.gif" alt="Loading..."> Waiting for other player...';
    
    function initBalloonInput() {
        // Reset the undo stack.
        undoStack = [];
        $("#balloon").html('<span class="variable" data-varname="' + ROOT_OPTION_ID + '">Click red buttons to speak</span>');
        selectNextVariable();
        enableSendButtonIfPossible();
    }
    
    function selectNextVariable() {
    
        var nextVariable = $("#balloon .variable").first();
        if (nextVariable.length !== 0 ) {
            nextVariable.trigger('click');
        } else {
            updateVariableButtons();
        }
    }
    
    function getUndoButton() {
        return $('<button>').attr('type','button')
                            .attr('title','Go back')
                            .data('option_id', UNDO_OPTION_ID)
                            .html('<img src="_/img/undo.png" alt="Go back" />');
    }
	    
    function getVariableButtons(varname) {
        var options = grammar[varname].slice(0);
        
        var buttons = [];
            
        // If there are options.
        if ( options.length !== 0 ) {
        
            var otherOptionIncluded = false;
            var randomizeOptionIncluded = false;
            var i;
            for (i = 0; i < options.length; i += 1) {
                
                // Get option_id.
                var option_id = i;
                
                // Make sure the other option is added last.
                if ( options[i] === OTHER_OPTION ) {
                    otherOptionIncluded = true;
                    continue;
                }
                
                // Check if there is a randomize option.
                if ( options[i] === RANDOMIZE_OPTION ) {
                    randomizeOptionIncluded = true;
                    continue;
                }
                
                // Get option_str.
                var option_str = options[i].replace(variableRegex, function(match, varname, _, pretty) {
                    if ( pretty !== '' && typeof pretty != 'undefined' ) {
                        return pretty;
                    } else {
                        return '...';
                    }
                });
                
                // Add new button to result.
                buttons.push($('<button>').attr('type','button')
                                          .attr('title',varname.replace(/_/g,' ') + ': ' + option_str)
                                          .data('option_id', option_id)
                                          .data('varname', varname)
                                          .text(option_str)
                                          .get(0));
            }
            
            if ( randomizeOptionIncluded ) {
                // Shuffle the buttons.
                buttons.shuffle();
            }
            
            if ( undoStack.length > 0 ) {
                buttons.unshift(getUndoButton().get(0));
            }
            
            // Add the other option if necessary.
            if ( otherOptionIncluded ) {
                buttons.push($('<button>').attr('type','button')
                                          .attr('title','Specify other ' + varname.replace(/_/g,' '))
                                          .data('option_id', OTHER_OPTION_ID)
                                          .data('varname', varname)
                                          .text(OTHER_OPTION)
                                          .get(0));            
            }
        }
            
        return buttons;    
    }
    
    function enableSendButtonIfPossible() {
        if ( $("#balloon > utterance").length > 0 && $("#balloon .variable").length == 0 ) {
            $("#speak").removeAttr('disabled');
            $("#balloon").append(' <span class="variable" data-varname="' + ROOT_OPTION_ID + '">Click red buttons to say more</span>');
            selectNextVariable();
        } else {                
            $("#speak").attr('disabled','disabled');
        }  
        
        updateButtonImages();
    }
    
    function updateVariableButtons() {
        var selectedVariable = $('#balloon .variable#selected');
        if ( selectedVariable.length !== 0 ) {
            var varname = selectedVariable.data('varname');
            $("#speak_input").html(getVariableButtons(varname))
                             .append(' (click to speak)');
        } else {
            $("#speak_input").html(getUndoButton())
                             .append(' (click to speak)');
        }
    }
    
    // Done defining helper functions for speech balloon.
    ////////////////////////////////////////////////////////////////////////////
    
    ////////////////////////////////////////////////////////////////////////////
    // Start defining event handlers for speech balloon.
    
    var undoStack = [];
    
    $("#balloon .variable").live('click', function () {
    
        if ( $(this).attr('id') !== 'selected' ) {
            // Remove old selection.
            $('#balloon .variable#selected').removeAttr('id');
            // Add new selection.
            $(this).attr('id', 'selected');
            
            // Update buttons.
            updateVariableButtons();
        }
    });
    
    $("#speak_input").on('click', 'button', function(eventObject) {
        //var value = eventObject.target.id;
        
        var value = $(this).data('option_id');
                    
        // Replace current content.
        if ( value === UNDO_OPTION_ID ) {
            var previous_option = undoStack.pop();
        
            if ( previous_option ) {
                // Set balloon content.
                $("#balloon").html(previous_option);
                
                // Set buttons content.
                updateVariableButtons();
                enableSendButtonIfPossible();
            }
        } else if ( value === OTHER_OPTION_ID ) {
        
            var varname = $(this).data('varname');  
            
            // Add current content to undoStack.
            undoStack.push($("#balloon").html());
            
            // Set buttons content.
            $('#speak_input').html(getUndoButton())
                         .append('<input id="other_input" placeholder="a ' + varname.replace(/_/g, ' ') + '" type="text" data-varname="' + varname + '">')
                         .append('<button id="other_input_submit" type="button">Submit</button>')
                         .append('<span id="other_hint">(5 words max., alphabet characters only)</span>');
        } else {
        
            var varname = $(this).data('varname');  
                
            // Add current content to undoStack.
            undoStack.push($("#balloon").html());
                                  
            var newValue = grammar[varname][value].replace(variableRegex, function(match, varname, _, pretty) {
                return grammar[varname] instanceof Array
                  ? '<span class="variable" data-varname="' + varname + '">' + (pretty !== '' && typeof pretty != 'undefined'?pretty:'...') + '</span>'
                  : match;
            });
            
            // Update new value for selected variable.
            $('#balloon .variable#selected').replaceWith('<' + varname + '>' + newValue + '</' + varname + '>');
            
            // Set buttons content.
            selectNextVariable();
            enableSendButtonIfPossible();
        }              
    });
    
    $("#speak_input").on('change', '#other_input', function(eventObject) {
        
        $(this).closest('.error').remove();
        var varname = $(this).data('varname');  
        
        if ( this.value.match(/^[A-Za-z'"\(\):; ]+$/) !== null ) {
            $('#balloon .variable#selected').replaceWith('<' + varname + '><' + OTHER_OPTION_ID + '>' + this.value + '</' + OTHER_OPTION_ID + '></' + varname + '>');
            $('#speak_input').empty();
        } else {
            this.value = '';
            $("#other_hint").addClass("error");
        }
        
        // Set buttons content.
        selectNextVariable();
        enableSendButtonIfPossible();
    });     
                
    function disableAllInput(message) {
        $("#balloon_buttons").hide();
        $("#balloon_wrapper").addClass('transparent');
        $("#speak_input").addClass('transparent').addClass('hidden');
        $("#speak_input").attr('disabled','disabled');
        $("#action_input").addClass('transparent').addClass('hidden');
        
        $("#balloon").html(message);
        
        updateButtonImages();
    }
    
    function setInput(ourTurn) {
        if ( ourTurn ) {
            // Enable/show all input.
            $("#balloon_buttons").show();
            $("#balloon_wrapper").removeClass('transparent');
            $("#speak_input").removeClass('transparent').removeClass('hidden');
            $("#speak_input").removeAttr('disabled');
            $("#action_input").removeClass('transparent').removeClass('hidden');
        
            initBalloonInput();
        } else {
            // Disable/hide all input.            
            disableAllInput(waitingHtml);
        }
    }  
        
    // Bind a submit handler to the form.
    $("#speak").click(function (eventObject) {
        // Send balloon.
        if ( $("#balloon > utterance").length > 0
            && $("#balloon .variable").length == 1
            && $("#balloon > .variable").length == 1 ) {
            // Save balloon input before we disable it.
            $("#balloon > .variable").remove();
            var balloonInput = $("#balloon").html();
        
            // Reset the undo stack.
            undoStack = [];
            // End current player's turn.
            setInput(false);
            
            post_action("new_chat", {line: balloonInput}, function () {
                // Load and animate chat log.
                loadChat(true);
            });
        }
    });
    
    // Bind a handler to clicking the Clear all button.
    $("#clear").click(function () {
        initBalloonInput();
    });
    
    // Done defining event handlers for speech balloon.
    ////////////////////////////////////////////////////////////////////////////

    function loadChat(animate) {
        $("#chat").load("chat.php", function() {
            if ( animate === true ) {
                $("#chat .balloon:first").fadeTo(0, 0);
                $("#chat .balloon:first").fadeTo('slow', 1);
            }
        });
    }
    
    function updateButtonImages() {
        // Make sure all our disabled button images are also greyed out. And vice versa.
        $('button:enabled > img').css('opacity',1.0);
        $('button:disabled > img').css('opacity',0.5);
    }
    
    function slideSwitch() {
        var $active = $('#slideshow img.active');

        if ( $active.length == 0 ) $active = $('#slideshow img:last');

        var $next =  $active.next().length ? $active.next()
            : $('#slideshow img:first');

        $active.addClass('last-active');

        $next.css({opacity: 0.0})
            .addClass('active')
            .animate({opacity: 1.0}, 1000, function() {
                $active.removeClass('active last-active');
            });
    }

    // Start Comet loop.
    get_events(function (events) {
        $("#debug").text('Debug: ');
        var i;
        var event;
        for (i = 0; i < events.length; i += 1) {
            event = events[i];
            $("#debug").append(event.event_name + " ").css("color", "red");
            if (event.event_name === "new_log") {  
                
                animalPaid = event.animal_paid;
                animalGiven = event.animal_given;
                
                if ( animalPaid ? !animalGiven : animalGiven ) {
                    $("#leave").attr('disabled', 'disabled');
                } else {
                    $("#leave").removeAttr('disabled');                
                }
                          
                // Start current player's turn.
                loadChat(true);
                setInput(true);
            }
            
            if (event.event_name === "waiting_for_other_player") {
                $("#status").html(waitingHtml);
            }
            
            if (event.event_name === "show_slideshow") {
                // Test our role.
                playerRole = event.role;               
                
                $("#status").html('Showing slideshow and assignment for ' + playerRole + '. Click to proceed if you\'re done: <button type="button" id="proceed">Proceed</button>');
            
                $("#slideshow").append('<img src="_/img/cat.jpg" alt="cat" />');
                $("#slideshow").append('<img src="_/img/dog.jpg" alt="dog" />');
                $("#slideshow").append('<img src="_/img/goldfish.jpg" alt="goldfish" />');
                $("#slideshow").append('<img src="_/img/hamster.jpg" alt="hamster" />');
                $("#slideshow").append('<img src="_/img/parrot.jpg" alt="parrot" />');

                setInterval(slideSwitch, 5000 );
        
                $("#proceed").click(function () {
                    $("#status").empty();
                    post_action("proceed");
                });
            }   
            
            if ( !gameStarted && event.event_name === "start_game") {
                
                gameStarted = true;
                
                animalPaid = event.animal_paid;
                animalGiven = event.animal_given;
                
                $("#slideshow").remove();
                $("#game").show();                
                         
                // Get our role.
                playerRole = event.role;  
                $("#status").html('I am a ' + playerRole);
                // Test our role.
                if ( playerRole == ROLE_CUSTOMER ) {
                    reloadGrammar(customer_grammar);
                    
                    $("#action_input").prepend('<button type="button" title="Pay the shopkeep for the animal he has given or will give to you. You can only do this once per conversation and it will end your current turn." id="pay"><img src="_/img/pay.png" alt="Pay" /> Pay for animal</button>');
                    $("#pay").click(function() {

                        // Confirm customer wants to pay.
                        if ( !confirm("Paying the shopkeep can only be done once during the conversation. Are you sure?") ) {
                            return;
                        }

                        $(this).attr('disabled', 'disabled');                        
                        if ( !animalGiven ) {
                            $("#leave").attr('disabled', 'disabled');                        
                        }

                        post_action("pay");
                        // Load and animate chat log.
                        loadChat(true);
                        // End current player's turn.
                        setInput(false);
                    })
                    if ( animalPaid ) {
                        $("#pay").attr('disabled', 'disabled');
                    }
                    
                    $("#action_input").prepend('<button type="button" title="Leave the pet shop. This will end the conversation." id="leave"><img src="_/img/leave.png" alt="Leave" /> Leave pet shop</button>');
                    $("#leave").click(function() {

                        // Confirm customer wants to leave.
                        if ( !confirm("Leaving the pet shop will end the conversation. Continue?") ) {
                            return;
                        }

                        $(this).attr('disabled', 'disabled');
                        
                        post_action("leave");
                        // Load and animate chat log.
                        loadChat(true);
                        // End current player's turn.
                        setInput(false);
                    });
                    if ( animalPaid ? !animalGiven : animalGiven ) {
                        $("#leave").attr('disabled', 'disabled');
                    }
                } else if ( playerRole == ROLE_SHOPKEEP ) {          
                    reloadGrammar(shopkeep_grammar);
                    $("#action_input").prepend('<button type="button" title="Give an animal to the customer. You can only do this once per conversation and it will end your current turn." id="give_animal"><img src="_/img/pet.png" alt="Pet" /> Give animal</button>');
                    $("#give_animal").click(function() {

                        // Confirm shopkeep wants to give the animal.
                        if ( !confirm("Giving an animal to the customer can only be done once during the conversation. Are you sure?") ) {
                            return;
                        }

                        $(this).attr('disabled', 'disabled');
                        post_action("give_animal");
                        // Load and animate chat log.
                        loadChat(true);
                        // End current player's turn.
                        setInput(false);
                    });
                    if ( animalGiven ) {
                        $("#give_animal").attr('disabled', 'disabled');
                    }
                }
                
                // Change input fields based on whether it's our turn or not.
                setInput(event.turn);
                                
                // Reload chat log.
                loadChat(false);
            }
            
            if (event.event_name === "go_to_evaluation") {
                // Disable all input to be sure.
            
                $("#slideshow").remove();
                $("#game").show();
                loadChat(false);
                setInput(false);
                $("#action_input").append('<button type="button" id="bogus"></button>');
                
                // Add message and button to proceed to evaluation.
                disableAllInput('The conversation is over, redirecting to questionnaire... <button type="button" id="proceed_eval">Proceed</button>');
                $("#proceed_eval").click(function() {
                    // Redirect player to evaluation form.
                    window.onbeforeunload = null;
                    window.location.replace("eval.php");                                    
                });
                
                // If the button isn't clicked within 5 seconds, click it automatically.
                setTimeout(function() {
                    $("#proceed_eval").click();
                }, 5000);
            }

            if (event.event_name === "other_player_afk") {

                if ( gameStarted ) {
                    // Disable all input to be sure.

                    $("#slideshow").remove();
                    $("#game").show();
                    loadChat(false);
                    setInput(false);
                    $("#action_input").append('<button type="button" id="bogus"></button>');

                    // Add message and button to proceed to evaluation.
                    disableAllInput('Other player timed out, redirecting to a new game... <button type="button" id="proceed_game">Proceed</button>');
                    $("#proceed_game").click(function() {
                        // Redirect player to evaluation form.
                        window.onbeforeunload = null;
                        window.location.replace("game.php");
                    });

                    // If the button isn't clicked within 5 seconds, click it automatically.
                    setTimeout(function() {
                        $("#proceed_game").click();
                    }, 5000);
                } else {
                    // Redirect player to evaluation form.
                    window.onbeforeunload = null;
                    window.location.replace("game.php");
                }
            }
            
            updateButtonImages();
        }
    });
    
    // Prep the page.
    $("#balloon_wrapper").addClass('balloon');
    $("#speak_input").html(getUndoButton());
        
    $("#game").hide();
    //TODO$("#debug").hide();

    // Let the game know we're here.
    post_action("page_loaded");
    
});
