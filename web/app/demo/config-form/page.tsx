"use client"

import { useState } from "react"

import { ConfigForm } from "@/components/config-form/config-form"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  configFormDemoSchema,
  type ConfigFormDemoValues,
} from "@/lib/schemas/config-form-demo"
import type { FieldConfig } from "@/lib/forms/types"
import { submitDemoForm } from "@/app/demo/config-form/demo-action"

/**
 * Standalone demo/example for `<ConfigForm>` (KOZ-17's DoD requires at
 * least one). Not a real screen — a dedicated page to show the component
 * working end-to-end with text, select, checkbox, a multiple-select
 * combobox, and a `visibleWhen`-conditional field, independent of any
 * login/CRUD screen (those are KOZ-18+).
 *
 * Try:
 * - switching "Accounttype" to "Zakelijk" to reveal the conditional
 *   "Bedrijfsnaam" field (`visibleWhen`), required via the schema's
 *   `.superRefine()`
 * - submitting with e-mail "taken@example.nl" to see a field-level error
 *   returned by the (fake) action, rendered through the same field-wrapper
 *   as a validation error
 * - selecting multiple interests in the combobox
 */
const fields: FieldConfig<ConfigFormDemoValues>[] = [
  {
    name: "fullName",
    label: "Naam",
    type: "text",
    placeholder: "Jan Jansen",
    autoComplete: "name",
  },
  {
    name: "email",
    label: "E-mailadres",
    type: "email",
    placeholder: "jan@bedrijf.nl",
    hint: "Probeer taken@example.nl om een field-level fout van de action te zien.",
    autoComplete: "email",
  },
  {
    name: "accountType",
    label: "Accounttype",
    type: "select",
    placeholder: "Kies een accounttype",
    options: [
      { value: "personal", label: "Persoonlijk" },
      { value: "business", label: "Zakelijk" },
    ],
  },
  {
    name: "companyName",
    label: "Bedrijfsnaam",
    type: "text",
    placeholder: "Acme BV",
    hint: "Alleen verplicht bij een zakelijk account.",
    visibleWhen: (values) => values.accountType === "business",
  },
  {
    name: "plan",
    label: "Abonnement",
    type: "select",
    placeholder: "Kies een abonnement",
    options: [
      { value: "solo", label: "Solo" },
      { value: "team", label: "Team" },
      { value: "enterprise", label: "Enterprise" },
    ],
  },
  {
    name: "interests",
    label: "Interesses",
    type: "combobox",
    multiple: true,
    placeholder: "Kies één of meer interesses",
    options: [
      { value: "design", label: "Design" },
      { value: "development", label: "Development" },
      { value: "sales", label: "Sales" },
      { value: "support", label: "Support" },
    ],
  },
  {
    name: "bio",
    label: "Over jou",
    type: "textarea",
    placeholder: "Een korte introductie...",
    hint: "Maximaal 280 tekens.",
    rows: 4,
  },
  {
    name: "subscribeToNewsletter",
    label: "Nieuwsbrief ontvangen",
    type: "checkbox",
  },
]

const defaultValues: ConfigFormDemoValues = {
  fullName: "",
  email: "",
  accountType: "personal",
  companyName: "",
  plan: "",
  interests: [],
  bio: "",
  subscribeToNewsletter: false,
}

export default function ConfigFormDemoPage() {
  const [lastSubmission, setLastSubmission] = useState<ConfigFormDemoValues | null>(null)

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-6 p-8">
      <div>
        <h1 className="text-2xl font-bold">ConfigForm demo</h1>
        <p className="text-muted-foreground">
          Losstaand voorbeeld van het generieke formulier-component (KOZ-17).
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Account aanmaken</CardTitle>
          <CardDescription>
            Config-object + één Zod-schema, gerenderd/gevalideerd/afgehandeld door
            &lsquo;ConfigForm&rsquo;.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ConfigForm
            fields={fields}
            schema={configFormDemoSchema}
            defaultValues={defaultValues}
            action={submitDemoForm}
            onSuccess={(_result, values) => setLastSubmission(values)}
            submitLabel="Account aanmaken"
            pendingLabel="Bezig met versturen..."
          />
        </CardContent>
      </Card>

      {lastSubmission && (
        <Card>
          <CardHeader>
            <CardTitle>Laatst verzonden</CardTitle>
            <CardDescription>Resultaat van de submit-actie.</CardDescription>
          </CardHeader>
          <CardContent>
            <pre className="overflow-x-auto rounded-md bg-muted p-4 text-xs">
              {JSON.stringify(lastSubmission, null, 2)}
            </pre>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
