"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { ConfigForm } from "@/components/config-form/config-form"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  buildConfigFormDemoSchema,
  type ConfigFormDemoValues,
} from "@/lib/schemas/config-form-demo"
import type { FieldConfig } from "@/lib/forms/types"
import type { Locale } from "@/lib/i18n/locale"
import type { TFunction } from "i18next"
import { submitDemoForm } from "@/app/demo/config-form/demo-action"

/**
 * Standalone demo/example page for `<ConfigForm>`, not a real screen —
 * reachable only at /demo/config-form, not linked from any nav. Translated
 * anyway (KOZ-29 rework: no page gets a silent "out of scope" exemption
 * without explicit sign-off), including its `<ConfigForm>` field config.
 */
function buildFields(t: TFunction): FieldConfig<ConfigFormDemoValues>[] {
  return [
    {
      name: "fullName",
      label: t("demo.fullNameLabel"),
      type: "text",
      placeholder: t("demo.fullNamePlaceholder"),
      autoComplete: "name",
    },
    {
      name: "email",
      label: t("demo.emailLabel"),
      type: "email",
      placeholder: t("demo.emailPlaceholder"),
      hint: t("demo.emailHint"),
      autoComplete: "email",
    },
    {
      name: "accountType",
      label: t("demo.accountTypeLabel"),
      type: "select",
      placeholder: t("demo.accountTypePlaceholder"),
      options: [
        { value: "personal", label: t("demo.accountTypePersonal") },
        { value: "business", label: t("demo.accountTypeBusiness") },
      ],
    },
    {
      name: "companyName",
      label: t("demo.companyNameLabel"),
      type: "text",
      placeholder: t("demo.companyNamePlaceholder"),
      hint: t("demo.companyNameHint"),
      visibleWhen: (values) => values.accountType === "business",
    },
    {
      name: "plan",
      label: t("demo.planLabel"),
      type: "select",
      placeholder: t("demo.planPlaceholder"),
      options: [
        { value: "solo", label: t("demo.planSolo") },
        { value: "team", label: t("demo.planTeam") },
        { value: "enterprise", label: t("demo.planEnterprise") },
      ],
    },
    {
      name: "interests",
      label: t("demo.interestsLabel"),
      type: "combobox",
      multiple: true,
      placeholder: t("demo.interestsPlaceholder"),
      options: [
        { value: "design", label: t("demo.interestDesign") },
        { value: "development", label: t("demo.interestDevelopment") },
        { value: "sales", label: t("demo.interestSales") },
        { value: "support", label: t("demo.interestSupport") },
      ],
    },
    {
      name: "bio",
      label: t("demo.bioLabel"),
      type: "textarea",
      placeholder: t("demo.bioPlaceholder"),
      hint: t("demo.bioHint"),
      rows: 4,
    },
    {
      name: "subscribeToNewsletter",
      label: t("demo.newsletterLabel"),
      optionLabel: t("demo.newsletterOptionLabel"),
      type: "checkbox",
    },
    {
      name: "contactPreference",
      label: t("demo.contactPreferenceLabel"),
      type: "radio",
      hint: t("demo.contactPreferenceHint"),
      hintPlacement: "belowLabel",
      options: [
        { value: "email", label: t("demo.contactPreferenceEmail") },
        { value: "phone", label: t("demo.contactPreferencePhone") },
        { value: "none", label: t("demo.contactPreferenceNone") },
      ],
    },
  ]
}

const defaultValues: ConfigFormDemoValues = {
  fullName: "",
  email: "",
  accountType: "personal",
  companyName: "",
  plan: "",
  interests: [],
  bio: "",
  subscribeToNewsletter: false,
  contactPreference: "",
}

export default function ConfigFormDemoPage() {
  const { t, i18n } = useTranslation()
  const [lastSubmission, setLastSubmission] = useState<ConfigFormDemoValues | null>(null)
  const fields = useMemo(() => buildFields(t), [t])
  const schema = useMemo(() => buildConfigFormDemoSchema(i18n.language as Locale), [i18n.language])

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-6 p-8">
      <div>
        <h1 className="text-2xl font-bold">{t("demo.pageTitle")}</h1>
        <p className="text-muted-foreground">
          {t("demo.pageSubtitle")}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("demo.cardTitle")}</CardTitle>
          <CardDescription>
            {t("demo.cardDescription")}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ConfigForm
            fields={fields}
            schema={schema}
            defaultValues={defaultValues}
            action={submitDemoForm}
            onSuccess={(_result, values) => setLastSubmission(values)}
            submitLabel={t("demo.submitLabel")}
            pendingLabel={t("demo.pendingLabel")}
          />
        </CardContent>
      </Card>

      {lastSubmission && (
        <Card>
          <CardHeader>
            <CardTitle>{t("demo.lastSubmissionTitle")}</CardTitle>
            <CardDescription>{t("demo.lastSubmissionDescription")}</CardDescription>
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
