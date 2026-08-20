<?php

return [
    'required' => 'Поле :attribute обязательно.',
    'required_if' => 'Поле :attribute обязательно.',
    'required_without' => 'Поле :attribute обязательно, если :values не заполнено.',
    'email' => 'Поле :attribute должно содержать корректный e-mail.',
    'unique' => 'Такое значение поля :attribute уже используется.',
    'exists' => 'Выбранное значение поля :attribute недопустимо.',
    'date' => 'Поле :attribute должно содержать корректную дату.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'min' => ['numeric' => 'Поле :attribute должно быть не меньше :min.', 'string' => 'Поле :attribute должно содержать не меньше :min символов.'],
    'max' => ['numeric' => 'Поле :attribute не должно превышать :max.', 'string' => 'Поле :attribute не должно превышать :max символов.', 'file' => 'Размер файла не должен превышать :max КБ.'],
    'mimetypes' => 'Тип файла :attribute не поддерживается.',
    'attributes' => [],
];
