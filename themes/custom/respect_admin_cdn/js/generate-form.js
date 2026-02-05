

function createInput(field, value = '',state) {
  const input = document.createElement("input");
  input.name = field.name;
  input.type = field.type === "phone" ? "tel" : field.type;
  input.required = !!field.required;
  input.value = value;
  input.className = "p-2 border rounded";
  input.oninput = e => {
    state[field.name] = e.target.value;
    
  };
  return input;
}


function createButton(field) {
  const input = document.createElement("input");
  input.name = field.name;
  input.type = field.type;
  input.value = field.label;
  input.required = !!field.required;
  input.className = field.class;
  return input;
}

function createSelect(field, value = '',state) {
  const select = document.createElement("select");
  select.name = field.name;
  select.required = !!field.required;
  select.className = "p-2 border rounded";

  const placeholder = document.createElement("option");
  placeholder.disabled = true;
  placeholder.selected = !value;
  placeholder.textContent = "Select";
  select.appendChild(placeholder);

  field.options.forEach(opt => {
    const option = document.createElement("option");
    option.value = opt;
    option.textContent = opt;
    if (value === opt) option.selected = true;
    select.appendChild(option);
  });

  select.onchange = e => {
    state[field.name] = e.target.value;
   
  };

  return select;
}

function renderForm(form, config,state) {
  form.innerHTML = "";
  if(config.destination){
    form.innerHTML = "<input type='hidden' name='destination__element' value='"+config.destination+"'/>";
  }

  config.fields.forEach(field => {
    if (field.showIf && state[field.showIf.field] !== field.showIf.value) return;

    const wrapper = document.createElement("div");
    wrapper.className = "flex flex-col";

    const label = document.createElement("label");
    label.className = "mb-1 font-medium";
    label.textContent = field.label;

    let inputElement;
    const currentValue = state[field.name] || '';

    switch (field.type) {
      case "text":
      case "email":
      case "number":
      case "phone":
        inputElement = createInput(field, currentValue,state);
        wrapper.append(label, inputElement);
        break;
      case "textarea":
        const textarea = document.createElement("textarea",state);
        textarea.name = field.name;
        textarea.value = currentValue;
        textarea.className = "p-2 border rounded";
        textarea.oninput = e => {
          state[field.name] = e.target.value;
        };
        wrapper.append(label, textarea);
        break;
      case "image":
        const imageInput = document.createElement("input",state);
        imageInput.type = "file";
        imageInput.accept = "image/*";
        imageInput.name = field.name;
        imageInput.className = "p-2 border rounded";
        imageInput.onchange = e => {
          const file = e.target.files[0];
          if (file) {
            state[field.name] = file.name;
          }
        };
        wrapper.append(label, imageInput);
        break;

      case "select":
        inputElement = createSelect(field, currentValue,state);
        wrapper.append(label, inputElement);

        break;
      case "submit":
        inputElement = createButton(field);
        wrapper.append(label, inputElement);

        break;
      case "checkbox":
        const boxWrapper = document.createElement("div");
        boxWrapper.className = "flex items-center space-x-2";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.name = field.name;
        checkbox.checked = !!state[field.name];
        checkbox.onchange = e => {
          state[field.name] = e.target.checked;
          
        };

        const span = document.createElement("span");
        span.textContent = field.label;
        boxWrapper.append(checkbox, span);
        wrapper.appendChild(boxWrapper);
        break;
    }

    form.appendChild(wrapper);
  });

  config.buttons.forEach(button => {
    console.log(button);
    const wrapper = document.createElement("div");
    wrapper.className = "flex flex-col";

    const label = document.createElement("label");
    label.className = "mb-1 font-medium";
    label.textContent = button.label;

    let inputElement;
 
    switch (button.type) {
      case "submit":
        inputElement = createButton(button);
        wrapper.append(inputElement);
        inputElement.className = "mt-4 w-full bg-blue-500 text-white py-2 px-4 rounded";
      break;
     
        const boxWrapper = document.createElement("div");
        boxWrapper.className = "flex items-center space-x-2";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.name = field.name;
        checkbox.checked = !!state[field.name];
        checkbox.onchange = e => {
          state[field.name] = e.target.checked;
          renderForm(form, config);
        };

        const span = document.createElement("span");
        span.textContent = field.label;
        boxWrapper.append(checkbox, span);
        wrapper.appendChild(boxWrapper);
        break;
    }
    form.appendChild(wrapper);

  });
}



