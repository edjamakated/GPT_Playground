<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="GPT Chat Playground">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPT Chat</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>

<body>
    <section class="section">
        <div class="container">
            <h1 class="title">
                GPT Playground
            </h1>
            <p class="subtitle">
                Engineering <strong>Prompts</strong>!
            </p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <form id="chat-form" class="form">
                <div class="instruction-set">
                    <div class="field">
                        <label class="label">User Instructions</label>
                        <div class="control">
                            <input type="text" name="user_instructions[]" class="input" placeholder="User Instructions" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Assistant Instructions</label>
                        <div class="control">
                            <input type="text" name="assistant_instructions[]" class="input" placeholder="Assistant Instructions" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">System Instructions</label>
                        <div class="control">
                            <input type="text" name="system_instructions[]" class="input" placeholder="System Instructions" required>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="model" value="gpt-3.5-turbo">
                <input type="hidden" id="temperature" value="0.06">
                <input type="hidden" id="max_tokens" value="2999">
                <input type="hidden" id="top_p" value="1">
                <input type="hidden" id="frequency_penalty" value="0">
                <input type="hidden" id="presence_penalty" value="0">
                <div class="field is-grouped">
                    <div class="control">
                        <button type="submit" class="button is-primary">Send</button>
                    </div>
                    <div class="control">
                        <button type="button" id="add-instruction-set" class="button is-info">Add Instruction Set</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div id="chat-window" class="box" style="height: 300px; overflow-y: scroll; padding: 5px; margin-bottom: 10px;">
            </div>
        </div>
    </section>
    <script>
        (function() {
            $(document).ready(function() {
                async function sendInstruction(instructionData) {
                    try {
                        const response = await fetch('api_async.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(instructionData),
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const eventSource = new EventSource(response.url);

                        eventSource.onmessage = function(event) {
                            const data = JSON.parse(event.data);

                            $('#chat-window').append('<p><strong>User:</strong> ' + data.userInstruction + '</p>');
                            $('#chat-window').append('<p><strong>Assistant:</strong> ' + data.assistantResponse + '</p>');
                            $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
                        };

                        eventSource.onerror = function(event) {
                            eventSource.close();
                        };
                    } catch (error) {
                        console.error('Error occurred during fetch: ', error);
                    }
                }

                $("#chat-form").on("submit", async function(e) {
                    e.preventDefault();

                    const formData = $(this).serializeArray();
                    const groupedData = {
                        user_instructions: [],
                        assistant_instructions: [],
                        system_instructions: []
                    };

                    formData.forEach(({
                        name,
                        value
                    }) => {
                        if (name === "user_instructions[]") groupedData.user_instructions.push(value);
                        if (name === "assistant_instructions[]") groupedData.assistant_instructions.push(value);
                        if (name === "system_instructions[]") groupedData.system_instructions.push(value);
                    });

                    const model = $("#model").val();
                    const temperature = $("#temperature").val();
                    const max_tokens = $("#max_tokens").val();
                    const top_p = $("#top_p").val();
                    const frequency_penalty = $("#frequency_penalty").val();
                    const presence_penalty = $("#presence_penalty").val();

                    await sendInstruction({
                        ...groupedData,
                        model,
                        temperature,
                        max_tokens,
                        top_p,
                        frequency_penalty,
                        presence_penalty
                    });

                    $("input[name='user_instructions[]']").val('');
                    $("input[name='assistant_instructions[]']").val('');
                    $("input[name='system_instructions[]']").val('');
                });

                $("#add-instruction-set").on("click", function() {
                    const newInstructionSet = `
    <div class="instruction-set">
        <div class="field">
            <label class="label">User Instructions</label>
            <div class="control">
                <input type="text" name="user_instructions[]" class="input" placeholder="User Instructions" required>
            </div>
        </div>
        <div class="field">
            <label class="label">Assistant Instructions</label>
            <div class="control">
                <input type="text" name="assistant_instructions[]" class="input" placeholder="Assistant Instructions" required>
            </div>
        </div>
        <div class="field">
            <label class="label">System Instructions</label>
            <div class="control">
                <input type="text" name="system_instructions[]" class="input" placeholder="System Instructions" required>
            </div>
        </div>
    </div>`;
                    $("#chat-form .instruction-set:last").after(newInstructionSet);
                });
            });
        })();
    </script>
</body>

</html>
